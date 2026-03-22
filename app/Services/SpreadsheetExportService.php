<?php

namespace App\Services;

use App\Models\FinancialYear;
use App\Models\MemberFinancial;
use App\Models\Payment;
use App\Models\Expense;
use App\Models\BankBalance;
use App\Models\ExpenseCategory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class SpreadsheetExportService
{
    // Column layout constants — matches the original ledger format
    const COL_NO         = 'B';
    const COL_NAME       = 'C';
    const COL_PHONE      = 'D';
    const COL_BF         = 'E';
    const COL_MONTHS     = ['F','G','H','I','J','K','L','M','N','O','P','Q'];  // Jan–Dec
    const COL_CF         = 'R';
    const COL_WELFARE    = 'S';
    const COL_DEV        = 'T';
    const COL_OWING      = 'U';
    const COL_INVEST     = 'V';
    const COL_PCT        = 'W';

    const MONTHS = [1=>'JANUARY',2=>'FEBRUARY',3=>'MARCH',4=>'APRIL',5=>'MAY',6=>'JUNE',
                    7=>'JULY',8=>'AUGUST',9=>'SEPTEMBER',10=>'OCTOBER',11=>'NOVEMBER',12=>'DECEMBER'];

    // Colours matching the original spreadsheets
    const COLOR_HEADER_BG  = 'FF1A3A2A';   // dark forest green
    const COLOR_HEADER_FG  = 'FFD8F3DC';   // mist
    const COLOR_SUBHDR_BG  = 'FFD8F3DC';   // mist
    const COLOR_SUBHDR_FG  = 'FF1A3A2A';   // forest
    const COLOR_ALT_ROW    = 'FFF9FAF9';   // barely-there tint
    const COLOR_TOTAL_BG   = 'FFFFF3C7';   // amber tint for totals row
    const COLOR_SURPLUS_BG = 'FFD8F3DC';
    const COLOR_DEFICIT_BG = 'FFFEE2E2';

    public function export(FinancialYear $fy): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('YEAR ' . $fy->year);

        // Load data
        $financials = MemberFinancial::with('member')
            ->where('financial_year_id', $fy->id)
            ->orderByDesc('total_investment')
            ->get();

        $paymentsByMember = Payment::where('financial_year_id', $fy->id)
            ->get()
            ->groupBy('member_id')
            ->map(fn ($pays) => $pays->groupBy('month'));

        $bankBalances = BankBalance::where('financial_year_id', $fy->id)
            ->orderBy('month')->get()->keyBy('month');

        $expenses = Expense::with('expenseCategory')
            ->where('financial_year_id', $fy->id)
            ->get()
            ->groupBy('category');

        $catNames = ExpenseCategory::pluck('name', 'slug')->toArray();

        // ── Row 1: empty ────────────────────────────────────────────────────
        $row = 1;

        // ── Row 2: ledger title ──────────────────────────────────────────────
        $row = 2;
        $title = "LEDGER FOR ATHONI WELFARE ASSOCIATION AS AT 31ST DECEMBER {$fy->year}";
        $sheet->setCellValue("B{$row}", $title);
        $sheet->mergeCells("B{$row}:W{$row}");
        $sheet->getStyle("B{$row}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 13, 'color' => ['argb' => self::COLOR_HEADER_BG]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // ── Row 3: expected welfare note ────────────────────────────────────
        $row = 3;
        if ($fy->welfare_per_member) {
            $sheet->setCellValue("B{$row}", "EXPECTED WELFARE CONTRIBUTION YEAR {$fy->year} - PER PERSON");
            $sheet->setCellValue("W{$row}", $fy->welfare_per_member);
            $sheet->getStyle("B{$row}")->applyFromArray(['font' => ['italic' => true, 'size' => 10]]);
        }

        // ── Row 4: column headers ────────────────────────────────────────────
        $row = 4;
        $headers = [
            'B' => 'NO',
            'C' => 'MEMBERS NAME',
            'D' => 'Telephone No.',
            'E' => 'Total Contributions B/F',
        ];
        foreach (self::MONTHS as $m => $name) {
            $headers[self::COL_MONTHS[$m - 1]] = $name;
        }
        $headers['R'] = 'Total Contributions';
        $headers['S'] = 'Total Welfare';
        $headers['T'] = 'Dev.';
        $headers['U'] = 'Welfare Owing';
        $headers['V'] = 'Total Investment';
        $headers['W'] = '% age';

        foreach ($headers as $col => $label) {
            $sheet->setCellValue("{$col}{$row}", $label);
        }

        $headerRange = "B{$row}:W{$row}";
        $sheet->getStyle($headerRange)->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['argb' => self::COLOR_HEADER_FG], 'size' => 9],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::COLOR_HEADER_BG]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFB7E4C7']]],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(30);

        // ── Member data rows ──────────────────────────────────────────────────
        $dataStartRow = 5;
        $rowNum = $dataStartRow;
        $memberNo = 1;

        // Totals accumulators
        $totBF = $totCF = $totWelfare = $totDev = $totOwing = $totInvest = 0.0;
        $monthTotals = array_fill(1, 12, 0.0);

        foreach ($financials as $fin) {
            $member   = $fin->member;
            $payments = $paymentsByMember->get($member->id, collect());
            $isAlt    = ($memberNo % 2 === 0);

            $rowData = [
                'B' => $memberNo,
                'C' => $member->name,
                'D' => $member->phone,
                'E' => $fin->contributions_brought_forward ?: null,
            ];

            for ($m = 1; $m <= 12; $m++) {
                $monthPays = $payments->get($m, collect());
                $amt = (float) $monthPays->sum('amount');
                $rowData[self::COL_MONTHS[$m - 1]] = $amt ?: null;
                $monthTotals[$m] += $amt;
            }

            $rowData['R'] = $fin->contributions_carried_forward ?: null;
            $rowData['S'] = $fin->total_welfare ?: null;
            $rowData['T'] = $fin->development ?: null;
            $rowData['U'] = $fin->welfare_owing ?: null;
            $rowData['V'] = $fin->total_investment ?: null;
            $rowData['W'] = $fin->pct_share ?: null;

            foreach ($rowData as $col => $val) {
                if ($val !== null) $sheet->setCellValue("{$col}{$rowNum}", $val);
            }

            // Row styling
            $rowRange = "B{$rowNum}:W{$rowNum}";
            if ($isAlt) {
                $sheet->getStyle($rowRange)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB(self::COLOR_ALT_ROW);
            }
            // Highlight deficit rows
            if ($fin->welfare_owing < 0) {
                $sheet->getStyle("U{$rowNum}")->getFont()->getColor()->setARGB('FFDC2626');
            }

            // Number format for amounts
            foreach (['E','R','S','T','U','V'] as $col) {
                $sheet->getStyle("{$col}{$rowNum}")
                    ->getNumberFormat()->setFormatCode('#,##0;(#,##0);"-"');
            }
            $sheet->getStyle("W{$rowNum}")
                ->getNumberFormat()->setFormatCode('0.000000');
            foreach (self::COL_MONTHS as $col) {
                $sheet->getStyle("{$col}{$rowNum}")
                    ->getNumberFormat()->setFormatCode('#,##0;(#,##0);"-"');
            }
            $sheet->getStyle("B{$rowNum}:D{$rowNum}")
                ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle("E{$rowNum}:W{$rowNum}")
                ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            // Accumulate totals
            $totBF     += $fin->contributions_brought_forward;
            $totCF     += $fin->contributions_carried_forward;
            $totWelfare+= $fin->total_welfare;
            $totDev    += $fin->development;
            $totOwing  += $fin->welfare_owing;
            $totInvest += $fin->total_investment;

            $rowNum++;
            $memberNo++;
        }

        // ── Totals row ────────────────────────────────────────────────────────
        $totRow = $rowNum;
        $sheet->setCellValue("B{$totRow}", 'TOTAL');
        $sheet->setCellValue("E{$totRow}", $totBF);

        for ($m = 1; $m <= 12; $m++) {
            if ($monthTotals[$m] > 0) {
                $sheet->setCellValue(self::COL_MONTHS[$m - 1] . $totRow, $monthTotals[$m]);
            }
        }
        $sheet->setCellValue("R{$totRow}", $totCF);
        $sheet->setCellValue("S{$totRow}", $totWelfare);
        $sheet->setCellValue("T{$totRow}", $totDev ?: null);
        $sheet->setCellValue("U{$totRow}", $totOwing ?: null);
        $sheet->setCellValue("V{$totRow}", $totInvest);

        $sheet->getStyle("B{$totRow}:W{$totRow}")->applyFromArray([
            'font'    => ['bold' => true, 'size' => 10],
            'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::COLOR_TOTAL_BG]],
            'borders' => ['top' => ['borderStyle' => Border::BORDER_MEDIUM]],
        ]);

        // ── Bank balance rows ────────────────────────────────────────────────
        $bbRow = $totRow + 1;

        if ($bankBalances->count()) {
            $sheet->setCellValue("E{$bbRow}", 'Bank balance C/F');
            foreach ($bankBalances as $month => $bb) {
                $col = self::COL_MONTHS[$month - 1];
                $sheet->setCellValue("{$col}{$bbRow}", $bb->closing_balance);
                $sheet->getStyle("{$col}{$bbRow}")->getNumberFormat()
                    ->setFormatCode('#,##0.00');
            }
            $sheet->getStyle("B{$bbRow}:W{$bbRow}")->getFont()->setItalic(true)->setSize(9);
            $bbRow++;
        }

        // ── Expense rows ─────────────────────────────────────────────────────
        foreach ($expenses as $slug => $expGroup) {
            $catName = $catNames[$slug] ?? ucwords(str_replace('_', ' ', $slug));
            $sheet->setCellValue("E{$bbRow}", strtoupper($catName));
            foreach ($expGroup as $exp) {
                $col = self::COL_MONTHS[$exp->month - 1];
                $existing = $sheet->getCell("{$col}{$bbRow}")->getValue();
                $sheet->setCellValue("{$col}{$bbRow}", (($existing ?: 0) - $exp->amount));
            }
            $sheet->getStyle("B{$bbRow}:W{$bbRow}")->getFont()->setItalic(true)->setSize(9);
            $bbRow++;
        }

        // ── Column widths ────────────────────────────────────────────────────
        $sheet->getColumnDimension('B')->setWidth(6);
        $sheet->getColumnDimension('C')->setWidth(28);
        $sheet->getColumnDimension('D')->setWidth(14);
        $sheet->getColumnDimension('E')->setWidth(16);
        foreach (self::COL_MONTHS as $col) {
            $sheet->getColumnDimension($col)->setWidth(10);
        }
        $sheet->getColumnDimension('R')->setWidth(16);
        $sheet->getColumnDimension('S')->setWidth(14);
        $sheet->getColumnDimension('T')->setWidth(10);
        $sheet->getColumnDimension('U')->setWidth(14);
        $sheet->getColumnDimension('V')->setWidth(16);
        $sheet->getColumnDimension('W')->setWidth(10);

        // Freeze panes at header row
        $sheet->freezePane('E5');

        // ── Write to temp file ────────────────────────────────────────────────
        $path = storage_path("app/exports/YEAR_{$fy->year}_export.xlsx");
        @mkdir(dirname($path), 0755, true);

        $writer = new Xlsx($spreadsheet);
        $writer->save($path);

        return $path;
    }
}
