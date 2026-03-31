<?php

namespace App\Services;

use App\Models\FinancialYear;
use App\Models\Member;
use App\Models\Payment;
use App\Models\WelfareEvent;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class MonthlyImportService
{
    public function exportTemplate(int $year, int $month): string
    {
        $financialYear = FinancialYear::where('year', $year)->first();
        $members = Member::orderBy('name')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $monthName = Payment::MONTHS[$month] ?? "Month {$month}";
        $sheet->setTitle('Monthly Import');

        $sheet->setCellValue('A1', 'Athoni Welfare - Monthly Payments Import');
        $sheet->mergeCells('A1:E1');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 13, 'color' => ['argb' => 'FF1A3A2A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $sheet->setCellValue('A2', "Year: {$year}   |   Month: {$monthName}");
        $sheet->mergeCells('A2:E2');
        $sheet->getStyle('A2')->applyFromArray([
            'font' => ['italic' => true, 'size' => 10, 'color' => ['argb' => 'FF52b788']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $sheet->setCellValue('A3', 'Instructions: Fill Payment/Welfare. Keep Member Name and Telephone unchanged.');
        $sheet->mergeCells('A3:E3');
        $sheet->getStyle('A3')->applyFromArray([
            'font' => ['italic' => true, 'size' => 9, 'color' => ['argb' => 'FF6b7280']],
        ]);

        $sheet->setCellValue('A4', '__META__');
        $sheet->setCellValue('B4', $year);
        $sheet->setCellValue('C4', $month);
        $sheet->getRowDimension(4)->setVisible(false);

        $headers = [
            'A5' => 'Member Name',
            'B5' => 'Telephone',
            'C5' => 'Payment (KES)',
            'D5' => 'Welfare (KES)',
            'E5' => 'Notes',
        ];
        foreach ($headers as $cell => $label) {
            $sheet->setCellValue($cell, $label);
        }
        $sheet->getStyle('A5:E5')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFD8F3DC'], 'size' => 10],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1A3A2A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        $row = 6;
        foreach ($members as $member) {
            $existingPayment = 0.0;
            $existingWelfare = 0.0;
            if ($financialYear) {
                $existingPayment = (float) Payment::where('member_id', $member->id)
                    ->where('financial_year_id', $financialYear->id)
                    ->where('month', $month)
                    ->sum('amount');

                $existingWelfare = (float) WelfareEvent::where('member_id', $member->id)
                    ->where('financial_year_id', $financialYear->id)
                    ->whereMonth('event_date', $month)
                    ->sum('amount');
            }

            $sheet->setCellValue("A{$row}", $member->name);
            $sheet->setCellValue("B{$row}", $member->phone);

            if ($existingPayment > 0) {
                $sheet->setCellValue("C{$row}", $existingPayment);
                $sheet->getStyle("C{$row}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFD8F3DC');
            }

            if ($existingWelfare > 0) {
                $sheet->setCellValue("D{$row}", $existingWelfare);
                $sheet->getStyle("D{$row}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFFEF3C7');
            }

            if ($row % 2 === 0) {
                $sheet->getStyle("A{$row}:E{$row}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFF9FAFB');
            }

            $sheet->getStyle("A{$row}:E{$row}")->getBorders()
                ->getAllBorders()->setBorderStyle(Border::BORDER_THIN)
                ->getColor()->setARGB('FFE5E7EB');

            $row++;
        }

        $sheet->getColumnDimension('A')->setWidth(28);
        $sheet->getColumnDimension('B')->setWidth(18);
        $sheet->getColumnDimension('C')->setWidth(16);
        $sheet->getColumnDimension('D')->setWidth(16);
        $sheet->getColumnDimension('E')->setWidth(28);
        $sheet->freezePane('A6');

        $path = storage_path("app/exports/monthly_template_{$year}_{$month}.xlsx");
        @mkdir(dirname($path), 0755, true);
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    public function preview(string $filePath, int $year, int $month): array
    {
        return $this->runMonthlyImport($filePath, $year, $month, false);
    }

    public function import(string $filePath, int $year, int $month): array
    {
        return $this->runMonthlyImport($filePath, $year, $month, true);
    }

    private function runMonthlyImport(string $filePath, int $year, int $month, bool $persist): array
    {
        $results = [
            'status' => 'success',
            'mode' => $persist ? 'final' : 'preview',
            'summary' => [
                'members_created' => 0,
                'members_updated' => 0,
                'payments_created' => 0,
                'expenses_created' => 0,
                'failed_rows' => 0,
                'welfare_created' => 0,
                'welfare_skipped' => 0,
                'payments_skipped' => 0,
            ],
            'membersInfo' => [
                'existing_members' => [],
                'new_members' => [],
                'all_members' => [],
                'error_members' => [],
                'existing_count' => 0,
                'new_count' => 0,
                'total_count' => 0,
            ],
            'paymentsInfo' => [
                'records_count' => 0,
                'total_amount' => 0.0,
            ],
            'payments' => [
                'month' => $month,
                'month_name' => Payment::MONTHS[$month] ?? $month,
                'items' => [],
                'totals' => [
                    'records_count' => 0,
                    'total_amount' => 0.0,
                ],
            ],
            'expensesInfo' => [
                'records_count' => 0,
                'total_amount' => 0.0,
            ],
            'year' => $year,
            'month' => Payment::MONTHS[$month] ?? $month,
            'errors' => [],
        ];

        $financialYear = FinancialYear::where('year', $year)->first();
        if (!$financialYear) {
            $results['status'] = 'error';
            $results['errors'][] = "Financial year {$year} not found. Import yearly sheet first.";
            return $results;
        }

        $parsedRows = $this->parseMonthlyRows($filePath, $results);
        $results['payments']['items'] = array_values(array_filter(array_map(
            fn(array $row) => $row['payment_amount'] > 0 ? [
                'row' => $row['row'],
                'name' => $row['name'],
                'phone' => $row['phone'],
                'amount' => $row['payment_amount'],
            ] : null,
            $parsedRows
        )));
        $results['payments']['totals'] = $results['paymentsInfo'];
        if ($persist) {
            DB::transaction(function () use (&$results, $parsedRows, $financialYear, $year, $month): void {
                foreach ($parsedRows as $row) {
                    if (!empty($row['errors']) || !$row['matched_member_id']) {
                        $results['summary']['failed_rows']++;
                        continue;
                    }

                    if ($row['payment_amount'] > 0) {
                        $existingPayment = Payment::where('member_id', $row['matched_member_id'])
                            ->where('financial_year_id', $financialYear->id)
                            ->where('month', $month)
                            ->exists();

                        if ($existingPayment) {
                            $results['summary']['payments_skipped']++;
                        } else {
                            Payment::create([
                                'member_id' => $row['matched_member_id'],
                                'financial_year_id' => $financialYear->id,
                                'month' => $month,
                                'amount' => $row['payment_amount'],
                                'payment_type' => 'contribution',
                                'notes' => $row['notes'] ?: null,
                            ]);
                            $results['summary']['payments_created']++;
                        }
                    }

                    if ($row['welfare_amount'] > 0) {
                        $existingWelfare = WelfareEvent::where('member_id', $row['matched_member_id'])
                            ->where('financial_year_id', $financialYear->id)
                            ->whereYear('event_date', $year)
                            ->whereMonth('event_date', $month)
                            ->exists();

                        if ($existingWelfare) {
                            $results['summary']['welfare_skipped']++;
                        } else {
                            WelfareEvent::create([
                                'member_id' => $row['matched_member_id'],
                                'financial_year_id' => $financialYear->id,
                                'amount' => $row['welfare_amount'],
                                'reason' => 'general',
                                'event_date' => Carbon::create($year, $month, 1)->endOfMonth()->toDateString(),
                                'notes' => $row['notes'] ?: null,
                            ]);
                            $results['summary']['welfare_created']++;
                        }
                    }
                }
            });
        }

        $results['membersInfo']['existing_count'] = count($results['membersInfo']['existing_members']);
        $results['membersInfo']['new_count'] = count($results['membersInfo']['new_members']);
        $results['membersInfo']['total_count'] = count($results['membersInfo']['all_members']);

        if (!empty($results['errors']) || !empty($results['membersInfo']['error_members'])) {
            $results['status'] = 'warning';
        }

        return $results;
    }

    private function parseMonthlyRows(string $filePath, array &$results): array
    {
        $reader = new XlsxReader();
        $spreadsheet = $reader->load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestRow();

        $headerRow = 5;
        $dataStartRow = 6;
        for ($r = 1; $r <= min(12, $highestRow); $r++) {
            if (trim((string) $sheet->getCell("A{$r}")->getValue()) === '__META__') {
                $headerRow = $r + 1;
                $dataStartRow = $r + 2;
                break;
            }
        }

        $colMap = $this->detectMonthlyColumns($sheet, $headerRow);
        if (!isset($colMap['name']) || !isset($colMap['payment']) || !isset($colMap['welfare'])) {
            $results['errors'][] = 'Invalid monthly template format. Required columns are missing.';
            return [];
        }

        $allMembers = Member::select('id', 'name', 'phone')->get();
        $memberByNamePhone = [];
        $memberByName = [];
        $dbPhoneCounts = [];
        foreach ($allMembers as $member) {
            $nameKey = $this->normalizeName($member->name);
            $phoneKey = $this->cleanPhone($member->phone) ?? '';
            $memberByNamePhone["{$nameKey}|{$phoneKey}"] = $member;
            $memberByName[$nameKey][] = $member;
            if ($phoneKey !== '') {
                $dbPhoneCounts[$phoneKey] = ($dbPhoneCounts[$phoneKey] ?? 0) + 1;
            }
        }

        $sheetNameCounts = [];
        $sheetPhoneCounts = [];
        for ($r = $dataStartRow; $r <= $highestRow; $r++) {
            $name = trim((string) $sheet->getCell($colMap['name'] . $r)->getValue());
            if ($name === '') {
                continue;
            }
            $paymentAmount = $this->toFloat($sheet->getCell($colMap['payment'] . $r)->getValue());
            $welfareAmount = $this->toFloat($sheet->getCell($colMap['welfare'] . $r)->getValue());
            if ($paymentAmount <= 0 && $welfareAmount <= 0) {
                continue;
            }

            $nameKey = $this->normalizeName($name);
            $sheetNameCounts[$nameKey] = ($sheetNameCounts[$nameKey] ?? 0) + 1;

            if ($colMap['phone']) {
                $phone = $this->cleanPhone((string) $sheet->getCell($colMap['phone'] . $r)->getValue());
                if ($phone) {
                    $sheetPhoneCounts[$phone] = ($sheetPhoneCounts[$phone] ?? 0) + 1;
                }
            }
        }

        $parsedRows = [];
        for ($r = $dataStartRow; $r <= $highestRow; $r++) {
            $name = trim((string) $sheet->getCell($colMap['name'] . $r)->getValue());
            if ($name === '') {
                continue;
            }

            $phoneRaw = $colMap['phone'] ? (string) $sheet->getCell($colMap['phone'] . $r)->getValue() : '';
            $phone = $this->cleanPhone($phoneRaw);
            $paymentAmount = $this->toFloat($sheet->getCell($colMap['payment'] . $r)->getValue());
            $welfareAmount = $this->toFloat($sheet->getCell($colMap['welfare'] . $r)->getValue());
            $notes = $colMap['notes'] ? trim((string) $sheet->getCell($colMap['notes'] . $r)->getValue()) : '';

            if ($paymentAmount <= 0 && $welfareAmount <= 0) {
                continue;
            }

            $errors = [];
            $nameKey = $this->normalizeName($name);
            $member = null;

            if (($sheetNameCounts[$nameKey] ?? 0) > 1) {
                $errors[] = 'Duplicate member name in monthly sheet';
            }

            if ($phone && ($sheetPhoneCounts[$phone] ?? 0) > 1) {
                $errors[] = 'Duplicate phone in monthly sheet';
            }

            if ($phone && ($dbPhoneCounts[$phone] ?? 0) > 1) {
                $errors[] = 'Phone is duplicated in DB';
            }

            if ($phone) {
                $member = $memberByNamePhone["{$nameKey}|{$phone}"] ?? null;
                if (!$member) {
                    $errors[] = 'No existing member matched by name + telephone';
                }
            } else {
                // Backward compatibility for old templates with no phone column.
                $nameMatches = $memberByName[$nameKey] ?? [];
                if (count($nameMatches) === 1) {
                    $member = $nameMatches[0];
                } elseif (count($nameMatches) > 1) {
                    $errors[] = 'Name is ambiguous in DB. Use template with telephone column.';
                } else {
                    $errors[] = 'Member not found';
                }
            }

            $status = $member ? 'existing' : 'new';
            $memberInfo = [
                'row' => $r,
                'name' => $name,
                'phone' => $phone,
                'status' => $status,
            ];

            $results['membersInfo']['all_members'][] = $memberInfo;
            if ($status === 'existing') {
                $results['membersInfo']['existing_members'][] = $memberInfo;
            } else {
                $results['membersInfo']['new_members'][] = $memberInfo;
            }

            if (!empty($errors)) {
                $results['membersInfo']['error_members'][] = $memberInfo + ['errors' => $errors];
                foreach ($errors as $error) {
                    $results['errors'][] = "Row {$r}: {$error}";
                }
            }

            $results['paymentsInfo']['records_count'] += ($paymentAmount > 0 ? 1 : 0);
            $results['paymentsInfo']['total_amount'] += $paymentAmount;

            $parsedRows[] = [
                'row' => $r,
                'name' => $name,
                'phone' => $phone,
                'matched_member_id' => $member?->id,
                'status' => $status,
                'errors' => $errors,
                'payment_amount' => $paymentAmount,
                'welfare_amount' => $welfareAmount,
                'notes' => $notes,
            ];
        }

        return $parsedRows;
    }

    private function detectMonthlyColumns($sheet, int $headerRow): array
    {
        $highestCol = $sheet->getHighestColumn();
        $highestIdx = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestCol);

        $colMap = [
            'name' => null,
            'phone' => null,
            'payment' => null,
            'welfare' => null,
            'notes' => null,
        ];

        for ($i = 1; $i <= $highestIdx; $i++) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
            $label = strtoupper(trim((string) $sheet->getCell($col . $headerRow)->getValue()));
            if (str_contains($label, 'MEMBER NAME')) {
                $colMap['name'] = $col;
            } elseif (str_contains($label, 'TELEPHONE') || str_contains($label, 'PHONE')) {
                $colMap['phone'] = $col;
            } elseif (str_contains($label, 'PAYMENT')) {
                $colMap['payment'] = $col;
            } elseif (str_contains($label, 'WELFARE')) {
                $colMap['welfare'] = $col;
            } elseif (str_contains($label, 'NOTES')) {
                $colMap['notes'] = $col;
            }
        }

        return $colMap;
    }

    private function normalizeName(string $name): string
    {
        return strtolower(trim((string) preg_replace('/\s+/', ' ', $name)));
    }

    private function cleanPhone(?string $value): ?string
    {
        $phone = preg_replace('/[^0-9+]/', '', (string) $value);
        return strlen($phone) >= 9 ? $phone : null;
    }

    private function toFloat(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }
        $string = strtolower(trim((string) $value));
        if (in_array($string, ['nan', 'none', '-', '—'], true)) {
            return 0.0;
        }
        return (float) str_replace([',', ' '], '', $string);
    }
}
