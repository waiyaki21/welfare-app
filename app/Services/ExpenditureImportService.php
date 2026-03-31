<?php

namespace App\Services;

use App\Models\Expenditure;
use App\Models\FinancialYear;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ExpenditureImportService
{
    private const TOTAL_ROW_PATTERN = '/\b(grand total|totals|total)\b/i';

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
                'narrations_detected' => 0,
            ],
            'expenditures' => [
                'narrations' => [],
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
                        'narration' => $row['narration'],
                        'name' => $row['name'],
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
        $defaultNarration = $year === 2022 ? 'General' : null;
        $currentNarration = $defaultNarration;
        $groupedNarrations = [];
        $records = [];
        $errors = [];
        $failedRows = 0;
        $seenNarrations = [];

        foreach ($rows as $rowIndex => $row) {
            [$name, $amount, $nonEmptyCells] = $this->extractRowValues($row);
            $normalizedName = preg_replace('/\s+/', ' ', trim((string) $name));

            if ($this->isHeaderRow($row)) {
                continue;
            }

            if ($normalizedName === '' && $amount <= 0) {
                $currentNarration = $defaultNarration;
                continue;
            }

            if ($normalizedName !== '' && preg_match(self::TOTAL_ROW_PATTERN, $normalizedName)) {
                break;
            }

            if ($normalizedName === '' && $amount > 0) {
                $failedRows++;
                $errors[] = "Row " . ($rowIndex + 1) . " contains an amount but no expenditure name.";
                continue;
            }

            if ($normalizedName !== '' && $amount <= 0 && $nonEmptyCells === 1) {
                $currentNarration = $normalizedName;
                $seenNarrations[$normalizedName] = true;
                continue;
            }

            if ($normalizedName === '' || $amount <= 0) {
                continue;
            }

            $recordNarration = $currentNarration ?? $defaultNarration;
            if ($recordNarration) {
                $seenNarrations[$recordNarration] = true;
            }

            $record = [
                'row' => $rowIndex + 1,
                'year' => $year,
                'narration' => $recordNarration,
                'name' => $normalizedName,
                'amount' => $amount,
            ];

            $records[] = $record;
            $groupKey = $recordNarration ?: 'Unspecified';
            if (!isset($groupedNarrations[$groupKey])) {
                $groupedNarrations[$groupKey] = [
                    'records_count' => 0,
                    'total_amount' => 0.0,
                    'items' => [],
                ];
            }
            $groupedNarrations[$groupKey]['records_count']++;
            $groupedNarrations[$groupKey]['total_amount'] += $amount;
            $groupedNarrations[$groupKey]['items'][] = $record;
        }

        return [
            'overview' => [
                'year' => $year,
                'total_records' => count($records),
                'total_amount' => (float) array_sum(array_column($records, 'amount')),
                'narrations_detected' => count($seenNarrations),
            ],
            'expenditures' => [
                'narrations' => $groupedNarrations,
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

    private function isHeaderRow(Collection $row): bool
    {
        $values = $row->map(fn($value) => strtoupper(trim((string) $value)))->values();
        $joined = $values->implode(' ');
        if ($joined === '') {
            return false;
        }

        $hasAmount = str_contains($joined, 'AMOUNT');
        $hasNarration = str_contains($joined, 'NARRATION');
        $hasExpense = str_contains($joined, 'EXPENSE');

        return $hasAmount && ($hasNarration || $hasExpense);
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
            strtolower(trim((string) ($row['narration'] ?? ''))),
            strtolower(trim((string) ($row['name'] ?? ''))),
            (string) round((float) ($row['amount'] ?? 0), 2),
        ]);
    }

    private function extractRowValues(Collection $row): array
    {
        $name = '';
        $amount = 0.0;
        $nonEmpty = 0;

        foreach ($row as $cell) {
            $raw = trim((string) $cell);
            if ($raw !== '') {
                $nonEmpty++;
            }

            if ($name === '' && $raw !== '' && !is_numeric($raw)) {
                $name = $raw;
            }

            $numeric = $this->toFloat($cell);
            if ($numeric > 0 && $amount <= 0) {
                $amount = $numeric;
            }
        }

        return [$name, $amount, $nonEmpty];
    }
}
