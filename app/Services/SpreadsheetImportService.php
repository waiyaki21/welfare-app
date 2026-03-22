<?php

namespace App\Services;

use App\Models\Member;
use App\Models\FinancialYear;
use App\Models\MemberFinancial;
use App\Models\Payment;
use App\Models\Expense;
use App\Models\BankBalance;
use App\Models\ExpenseCategory;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\DB;

class SpreadsheetImportService
{
    const MONTH_KEYS = [
        'JANUARY' => 1,
        'FEBRUARY' => 2,
        'MARCH' => 3,
        'APRIL' => 4,
        'MAY' => 5,
        'JUNE' => 6,
        'JULY' => 7,
        'AUGUST' => 8,
        'SEPTEMBER' => 9,
        'OCTOBER' => 10,
        'NOVEMBER' => 11,
        'DECEMBER' => 12,
    ];

    const EXPENSE_LABELS = [
        'BANK / MPESA CHARGES' => 'bank_charges',
        'BANK / MPESA'        => 'bank_charges',
        'BANK CHARGES'        => 'bank_charges',
        'BANK CHARGE'         => 'bank_charges',
        'MPESA CHARGES'       => 'bank_charges',
        'SECRETARY'           => 'secretary_fee',
        'SECRETARIAL'         => 'secretary_fee',
        'ADMIN'               => 'admin',
        'AUDIT'               => 'audit',
        'LEGAL'               => 'audit',
        'ANNUAL GENERAL'      => 'agm',
        'AGM'                 => 'agm',
        'WELFARE TOKEN'       => 'welfare_token',
        'DEVELOPMENT EXPENSE' => 'development',
        'CIC INVESTMENT'      => 'cic_transfer',
        'CIC INTEREST'        => 'cic_dividend',
        'TRANSFER TO KCB'     => 'investment_transfer',
        'KCB MONEY'           => 'investment_transfer',
        'CBK TBILL'           => 'investment_transfer',
        'SAGANA'              => 'sagana',
    ];

    public function import(string $filePath): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $results = [
            'sheets_processed' => 0,
            'members_created'  => 0,
            'members_updated'  => 0,
            'payments_created' => 0,
            'expenses_created' => 0,
            'errors'           => [],
        ];

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $year = $this->detectYear($sheet->getTitle());
            if (!$year) continue;

            try {
                DB::transaction(fn() => $this->processSheet($sheet, $year, $results));
                $results['sheets_processed']++;
            } catch (\Throwable $e) {
                $results['errors'][] = "Sheet [{$sheet->getTitle()}]: " . $e->getMessage();
            }
        }

        return $results;
    }

    private function processSheet($sheet, int $year, array &$results): void
    {
        $rows = $sheet->toArray(null, true, true, false);

        [$headerRow, $colMap, $monthCols] = $this->detectHeaders($rows);
        if ($headerRow === null) {
            throw new \RuntimeException('Could not find header row');
        }

        $welfareSample = $this->detectWelfarePerMember($rows, $headerRow, $colMap);

        $fy = FinancialYear::updateOrCreate(
            ['year' => $year],
            ['sheet_name' => $sheet->getTitle(), 'welfare_per_member' => $welfareSample]
        );

        if (!FinancialYear::where('is_current', true)->exists()) {
            $fy->update(['is_current' => true]);
        }

        // Track TOTAL row position for parseBottomRows
        $totalRowIdx = $headerRow + 60;

        foreach ($rows as $rowIdx => $row) {
            if ($rowIdx <= $headerRow) continue;

            $no   = trim((string) ($row[$colMap['no']   ?? -1] ?? ''));
            $name = trim((string) ($row[$colMap['name'] ?? -1] ?? ''));

            if (!$name || !is_numeric($no)) continue;
            if (strtoupper(trim($name)) === 'TOTAL') {
                $totalRowIdx = $rowIdx;
                break;
            }

            $phone  = $this->cleanPhone($row[$colMap['phone'] ?? -1] ?? '');
            $normal = $this->normaliseName($name);

            $member = Member::whereRaw('LOWER(TRIM(name)) = ?', [strtolower($normal)])->first();

            if ($member) {
                if ($phone && !$member->phone) $member->update(['phone' => $phone]);
                $results['members_updated']++;
            } else {
                $member = Member::create([
                    'name'        => $normal,
                    'phone'       => $phone,
                    'joined_year' => $year,
                    'is_active'   => true,
                ]);
                $results['members_created']++;
            }

            // For 2024/2025 welfare_owing is in a different column — pick the best one
            $owing = $this->resolveOwing($row, $colMap, $year);

            MemberFinancial::updateOrCreate(
                ['member_id' => $member->id, 'financial_year_id' => $fy->id],
                [
                    'contributions_brought_forward' => $this->toFloat($row[$colMap['bf']     ?? -1] ?? 0),
                    'contributions_carried_forward' => $this->toFloat($row[$colMap['cf']     ?? -1] ?? 0),
                    'total_welfare'                 => $this->toFloat($row[$colMap['welfare'] ?? -1] ?? 0),
                    'development'                   => $this->toFloat($row[$colMap['dev']    ?? -1] ?? 0),
                    'welfare_owing'                 => $owing,
                    'total_investment'              => $this->toFloat($row[$colMap['invest'] ?? -1] ?? 0),
                    'pct_share'                     => $this->toFloat($row[$colMap['pct']    ?? -1] ?? 0),
                    'notes'                         => null,
                ]
            );

            Payment::where('member_id', $member->id)
                ->where('financial_year_id', $fy->id)
                ->delete();

            foreach ($monthCols as $monthNum => $colIdx) {
                $amt = $this->toFloat($row[$colIdx] ?? 0);
                if ($amt <= 0) continue;
                Payment::create([
                    'member_id'         => $member->id,
                    'financial_year_id' => $fy->id,
                    'month'             => $monthNum,
                    'amount'            => $amt,
                    'payment_type'      => 'contribution',
                ]);
                $results['payments_created']++;
            }
        }

        $this->parseBottomRows($rows, $totalRowIdx, $monthCols, $fy, $results);
    }

    // ── Header detection — works for 2022, 2023, 2024, 2025 layouts ──────────

    private function detectHeaders(array $rows): array
    {
        foreach ($rows as $rowIdx => $row) {
            $upper  = array_map(fn($v) => strtoupper(trim((string) $v)), $row);
            $joined = implode(' ', $upper);
            // Require MEMBERS NAME specifically — avoids title rows containing words like 'NEW MEMBER'
            if (!str_contains($joined, 'MEMBERS NAME')) continue;

            // Must have at least one month column to be the real header
            $hasMonth = false;
            foreach ($upper as $cell) {
                if (isset(self::MONTH_KEYS[$cell])) {
                    $hasMonth = true;
                    break;
                }
            }
            if (!$hasMonth) continue;

            $colMap    = [];
            $monthCols = [];

            foreach ($upper as $colIdx => $cell) {
                // Member number
                if (trim($cell) === 'NO' || trim($cell) === 'NO.')
                    $colMap['no'] = $colIdx;

                // Member name
                if (str_contains($cell, 'MEMBERS NAME') || str_contains($cell, 'MEMBER NAME'))
                    $colMap['name'] = $colIdx;

                // Phone
                if (str_contains($cell, 'TELEPHONE') || str_contains($cell, 'PHONE'))
                    $colMap['phone'] = $colIdx;

                // Brought forward — col with B/F in name
                if (str_contains($cell, 'B/F') && !isset($colMap['bf']))
                    $colMap['bf'] = $colIdx;

                // Carried forward — "Total Contributions C/F" (2022/23) or just "Total Contributions" (2024/25)
                // Exclude the B/F column which also contains "TOTAL CONTRIBUTIONS"
                if (str_contains($cell, 'C/F') && !str_contains($cell, 'B/F') && !isset($colMap['cf']))
                    $colMap['cf'] = $colIdx;
                // 2024/2025: plain "TOTAL CONTRIBUTIONS" with no B/F or C/F suffix
                if ($cell === 'TOTAL CONTRIBUTIONS' && !isset($colMap['cf']))
                    $colMap['cf'] = $colIdx;

                // Welfare — pick the TOTAL welfare column
                // 2022/2023: "TOTAL WELFARE"
                // 2024: "Total Welfare till end of April 2024" → we want the last/total welfare col
                // 2025: "TOTAL WELFARE AS AT 2024"
                if (str_contains($cell, 'TOTAL WELFARE') && !isset($colMap['welfare']))
                    $colMap['welfare'] = $colIdx;
                // Override with later total welfare columns (2024/2025 have multiple)
                if (str_contains($cell, 'TOTAL WELFARE'))
                    $colMap['welfare_last'] = $colIdx;

                // Development
                if ($cell === 'DEV.' || $cell === 'DEV')
                    $colMap['dev'] = $colIdx;

                // Welfare owing — multiple columns in 2024/2025; collect all
                if (str_contains($cell, 'OWING') || str_contains($cell, 'WELFARE OWING')) {
                    if (!isset($colMap['owing'])) $colMap['owing'] = $colIdx;
                    $colMap['owing_last'] = $colIdx; // track last owing col
                }

                // Investment
                if (str_contains($cell, 'INVESTMENT') && !str_contains($cell, 'WITHDRAWAL'))
                    $colMap['invest'] = $colIdx;

                // Percentage
                if (str_contains($cell, '% AGE') || trim($cell) === '%')
                    $colMap['pct'] = $colIdx;

                // Months
                if (isset(self::MONTH_KEYS[$cell]))
                    $monthCols[self::MONTH_KEYS[$cell]] = $colIdx;
            }

            // Use the LAST "Total Welfare" column as the authoritative welfare total
            if (isset($colMap['welfare_last']))
                $colMap['welfare'] = $colMap['welfare_last'];

            // Use the LAST "owing" column as authoritative (2024 has two owing cols)
            if (isset($colMap['owing_last']))
                $colMap['owing'] = $colMap['owing_last'];

            return [$rowIdx, $colMap, $monthCols];
        }

        return [null, [], []];
    }

    // ── Resolve welfare owing for multi-column sheets (2024/2025) ────────────

    private function resolveOwing(array $row, array $colMap, int $year): float
    {
        // For 2024/2025 the "Welfare Owing from May" column is the final one
        if (isset($colMap['owing_last'])) {
            $v = $this->toFloat($row[$colMap['owing_last']] ?? 0);
            if ($v != 0) return $v;
        }
        return $this->toFloat($row[$colMap['owing'] ?? -1] ?? 0);
    }

    // ── Bank balances + expense rows after TOTAL row ──────────────────────────

    private function parseBottomRows(array $rows, int $totalRowIdx, array $monthCols, FinancialYear $fy, array &$results): void
    {
        BankBalance::where('financial_year_id', $fy->id)->delete();
        Expense::where('financial_year_id', $fy->id)->delete();

        $bankClosing = [];

        foreach ($rows as $rowIdx => $row) {
            if ($rowIdx <= $totalRowIdx) continue;

            // Build label from first ~5 non-empty string cells
            $labelParts = [];
            foreach (array_slice($row, 0, 10) as $cell) {
                $s = strtoupper(trim((string) $cell));
                if ($s !== '' && $s !== 'NAN' && $s !== 'NONE') $labelParts[] = $s;
            }
            $label = implode(' ', $labelParts);
            if (empty($label)) continue;

            // Bank balance row
            if ((str_contains($label, 'BANK BAL') || str_contains($label, 'BANK BAL.'))) {
                foreach ($monthCols as $month => $colIdx) {
                    $val = $this->toFloat($row[$colIdx] ?? 0);
                    if (abs($val) > 0.01) $bankClosing[$month] = $val;
                }
                continue;
            }

            // Expense rows
            $category = $this->matchExpenseCategory($label);
            if (!$category) continue;

            foreach ($monthCols as $month => $colIdx) {
                $val = $this->toFloat($row[$colIdx] ?? 0);
                if (abs($val) < 0.01) continue;

                // Ensure the category exists in the DB (creates it if new)
                ExpenseCategory::findOrImport($category);

                Expense::create([
                    'financial_year_id' => $fy->id,
                    'month'             => $month,
                    'category'          => $category,
                    'amount'            => abs($val),
                ]);
                $results['expenses_created']++;
            }
        }

        foreach ($bankClosing as $month => $closing) {
            $opening = $bankClosing[$month - 1] ?? 0;
            BankBalance::updateOrCreate(
                ['financial_year_id' => $fy->id, 'month' => $month],
                ['closing_balance' => $closing, 'opening_balance' => $opening]
            );
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function detectYear(string $title): ?int
    {
        if (preg_match('/\b(20\d{2})\b/', $title, $m)) return (int) $m[1];
        return null;
    }

    private function detectWelfarePerMember(array $rows, int $headerRow, array $colMap): float
    {
        if (!isset($colMap['welfare'])) return 0.0;
        $values = [];
        foreach ($rows as $i => $row) {
            if ($i <= $headerRow) continue;
            $v = $this->toFloat($row[$colMap['welfare']] ?? 0);
            if ($v > 0) $values[] = (int) $v;
            if (count($values) >= 15) break;
        }
        if (!$values) return 0.0;
        $counts = array_count_values($values);
        arsort($counts);
        return (float) array_key_first($counts);
    }

    private function matchExpenseCategory(string $label): ?string
    {
        foreach (self::EXPENSE_LABELS as $needle => $cat) {
            if (str_contains($label, $needle)) return $cat;
        }
        return null;
    }

    private function normaliseName(string $name): string
    {
        return trim(preg_replace('/\s+/', ' ', $name));
    }

    private function cleanPhone($val): ?string
    {
        $phone = preg_replace('/[^0-9+]/', '', (string) $val);
        return strlen($phone) >= 9 ? $phone : null;
    }

    private function toFloat($val): float
    {
        if ($val === null || $val === '') return 0.0;
        $str = strtolower(trim((string) $val));
        if ($str === 'nan' || $str === 'none' || $str === '#n/a') return 0.0;
        return (float) str_replace([',', ' '], '', $str);
    }
}
