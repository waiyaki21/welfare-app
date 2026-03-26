<?php

namespace App\Services;

use App\Models\Expenditure;
use App\Models\FinancialYear;
use App\Models\Payment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ExpenditureImportService
{
    private const TOTAL_ROW_PATTERN = '/\b(grand total|totals|total)\b/i';

    private const MONTH_ALIASES = [
        1 => ['JANUARY', 'JAN'],
        2 => ['FEBRUARY', 'FEB'],
        3 => ['MARCH', 'MAR'],
        4 => ['APRIL', 'APR'],
        5 => ['MAY'],
        6 => ['JUNE', 'JUN'],
        7 => ['JULY', 'JUL'],
        8 => ['AUGUST', 'AUG'],
        9 => ['SEPTEMBER', 'SEP', 'SEPT'],
        10 => ['OCTOBER', 'OCT'],
        11 => ['NOVEMBER', 'NOV'],
        12 => ['DECEMBER', 'DEC'],
    ];

    public function preview(string $filePath, int $year): array
    {
        return $this->run($filePath, $year, false, []);
    }

    public function import(string $filePath, int $year, array $options = []): array
    {
        return $this->run($filePath, $year, true, $options);
    }

    private function run(string $filePath, int $year, bool $persist, array $options): array
    {
        $results = [
            'status' => 'success',
            'mode' => $persist ? 'final' : 'preview',
            'summary' => [
                'records_created' => 0,
                'failed_rows' => 0,
            ],
            'overview' => [
                'year' => $year,
                'total_records' => 0,
                'total_amount' => 0.0,
                'months_detected' => 0,
            ],
            'expenditures' => [
                'months' => [],
                'rows' => [],
                'totals' => [
                    'records_count' => 0,
                    'total_amount' => 0.0,
                ],
            ],
            'errors' => [],
        ];

        $reader = IOFactory::createReaderForFile($filePath);
        if (method_exists($reader, 'setReadDataOnly')) {
            $reader->setReadDataOnly(true);
        }
        $spreadsheet = $reader->load($filePath);
        $sheet = $spreadsheet->getSheet(0);

        $parsed = $this->parseSheet($sheet, $year);
        $results['overview'] = $parsed['overview'];
        $results['expenditures'] = $parsed['expenditures'];
        $results['errors'] = $parsed['errors'];
        $results['summary']['failed_rows'] = $parsed['failed_rows'];

        if ($persist) {
            $removed = array_fill_keys($options['removed_expenditures'] ?? [], true);

            DB::transaction(function () use (&$results, $parsed, $year, $removed): void {
                $financialYear = FinancialYear::firstOrCreate(
                    ['year' => $year],
                    ['sheet_name' => "Expenditures {$year}"]
                );

                Expenditure::where('financial_year_id', $financialYear->id)->delete();

                foreach ($parsed['expenditures']['rows'] as $row) {
                    if (isset($removed[$this->removalKey($row)])) {
                        continue;
                    }

                    Expenditure::create([
                        'financial_year_id' => $financialYear->id,
                        'name' => $row['name'],
                        'month' => $row['month'],
                        'amount' => $row['amount'],
                    ]);
                    $results['summary']['records_created']++;
                }
            });
        }

        if (!empty($results['errors'])) {
            $results['status'] = 'warning';
        }

        return $results;
    }

    private function parseSheet(Worksheet $sheet, int $year): array
    {
        $rows = $this->sheetRowsAsCollection($sheet);
        [$headerRow, $nameColumn, $monthCols] = $this->detectHeaders($rows);

        if ($headerRow === null || $nameColumn === null || $monthCols === []) {
            throw new \RuntimeException('Could not detect the expenditures table header.');
        }

        $groupedMonths = [];
        foreach (Payment::MONTHS as $monthName) {
            $groupedMonths[$monthName] = [
                'records_count' => 0,
                'total_amount' => 0.0,
                'items' => [],
            ];
        }

        $records = [];
        $errors = [];
        $failedRows = 0;
        $emptyStreak = 0;

        foreach ($rows->slice($headerRow + 1) as $rowIndex => $row) {
            $name = trim((string) $row->get($nameColumn, ''));
            $normalizedName = preg_replace('/\s+/', ' ', $name);

            $hasMonthValue = false;
            foreach ($monthCols as $colIndex) {
                if (abs($this->toFloat($row->get($colIndex, 0))) > 0.01) {
                    $hasMonthValue = true;
                    break;
                }
            }

            if ($normalizedName === '' && !$hasMonthValue) {
                $emptyStreak++;
                if ($emptyStreak >= 3) {
                    break;
                }
                continue;
            }
            $emptyStreak = 0;

            if ($normalizedName !== '' && preg_match(self::TOTAL_ROW_PATTERN, $normalizedName)) {
                break;
            }

            if ($normalizedName === '' && $hasMonthValue) {
                $failedRows++;
                $errors[] = "Row " . ($rowIndex + 1) . " contains amounts but no expenditure name.";
                continue;
            }

            foreach ($monthCols as $month => $colIndex) {
                $amount = $this->toFloat($row->get($colIndex, 0));
                if ($amount <= 0) {
                    continue;
                }

                $monthName = Payment::MONTHS[$month] ?? (string) $month;
                $record = [
                    'row' => $rowIndex + 1,
                    'year' => $year,
                    'name' => $normalizedName,
                    'month' => $month,
                    'month_name' => $monthName,
                    'amount' => $amount,
                ];

                $records[] = $record;
                $groupedMonths[$monthName]['records_count']++;
                $groupedMonths[$monthName]['total_amount'] += $amount;
                $groupedMonths[$monthName]['items'][] = $record;
            }
        }

        return [
            'overview' => [
                'year' => $year,
                'total_records' => count($records),
                'total_amount' => (float) array_sum(array_column($records, 'amount')),
                'months_detected' => count(array_filter($groupedMonths, fn(array $month) => $month['records_count'] > 0)),
            ],
            'expenditures' => [
                'months' => $groupedMonths,
                'rows' => $records,
                'totals' => [
                    'records_count' => count($records),
                    'total_amount' => (float) array_sum(array_column($records, 'amount')),
                ],
            ],
            'errors' => $errors,
            'failed_rows' => $failedRows,
        ];
    }

    private function detectHeaders(Collection $rows): array
    {
        foreach ($rows as $rowIndex => $row) {
            $upper = $row->map(fn($value) => strtoupper(trim((string) $value)))->values();
            $monthCols = [];
            $nameColumn = null;

            foreach ($upper as $colIndex => $cell) {
                if ($nameColumn === null && $cell !== '' && !preg_match(self::TOTAL_ROW_PATTERN, $cell) && $this->cellToMonth($cell) === null) {
                    $nameColumn = $colIndex;
                }

                $month = $this->cellToMonth($cell);
                if ($month !== null) {
                    $monthCols[$month] = $colIndex;
                }
            }

            if ($nameColumn !== null && count($monthCols) >= 2) {
                return [$rowIndex, $nameColumn, $monthCols];
            }
        }

        return [null, null, []];
    }

    private function cellToMonth(string $cell): ?int
    {
        foreach (self::MONTH_ALIASES as $month => $aliases) {
            foreach ($aliases as $alias) {
                if ($cell === $alias || str_contains($cell, $alias)) {
                    return $month;
                }
            }
        }

        return null;
    }

    private function sheetRowsAsCollection(Worksheet $sheet): Collection
    {
        $highestDataRow = max(1, $sheet->getHighestDataRow());
        $highestDataColumn = $sheet->getHighestDataColumn();
        $colIdx = Coordinate::columnIndexFromString($highestDataColumn);
        if ($colIdx < 1) {
            $highestDataColumn = 'A';
        }

        $range = "A1:{$highestDataColumn}{$highestDataRow}";
        $rawRows = $sheet->rangeToArray($range, null, true, true, false);

        return collect($rawRows)->map(fn(array $row) => collect($row)->values());
    }

    private function toFloat($value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        $string = strtolower(trim((string) $value));
        if (in_array($string, ['nan', 'none', '#n/a', '-', '—', 'â€”'], true)) {
            return 0.0;
        }

        return (float) str_replace([',', ' '], '', $string);
    }

    private function removalKey(array $row): string
    {
        return implode('|', [
            $row['row'] ?? '',
            strtolower(trim((string) ($row['name'] ?? ''))),
            (string) ($row['month'] ?? ''),
            (string) round((float) ($row['amount'] ?? 0), 2),
        ]);
    }
}
