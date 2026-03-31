<?php

namespace App\Services;

use App\Models\BankBalance;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FinancialYear;
use App\Models\Member;
use App\Models\MemberFinancial;
use App\Models\Payment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SpreadsheetImportService
{
    private const TOTAL_ROW_PATTERN = '/\b(grand total|totals|total)\b/i';
    private const BANK_BAL_PATTERN = '/\b(bank\s*bal|bank\s*balance)\b/i';
    private const BANK_BAL_MARKERS = [
        'bank balance c/f',
        'bank balancec/f',
        'bank bal.',
        'bank bal',
    ];

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

    private const EXPENSE_LABELS = [
        'BANK / MPESA CHARGES' => 'bank_charges',
        'BANK / MPESA' => 'bank_charges',
        'BANK CHARGES' => 'bank_charges',
        'BANK CHARGE' => 'bank_charges',
        'MPESA CHARGES' => 'bank_charges',
        'SECRETARY' => 'secretary_fee',
        'SECRETARIAL' => 'secretary_fee',
        'ADMIN' => 'admin',
        'AUDIT' => 'audit',
        'LEGAL' => 'audit',
        'ANNUAL GENERAL' => 'agm',
        'AGM' => 'agm',
        'WELFARE TOKEN' => 'welfare_token',
        'DEVELOPMENT EXPENSE' => 'development',
        'CIC INVESTMENT' => 'cic_transfer',
        'CIC INTEREST' => 'cic_dividend',
        'TRANSFER TO KCB' => 'investment_transfer',
        'KCB MONEY' => 'investment_transfer',
        'CBK TBILL' => 'investment_transfer',
        'SAGANA' => 'sagana',
    ];

    private array $sheetErrors = [];

    public function preview(string $filePath, array $options = []): array
    {
        return $this->runImport($filePath, false, $options);
    }

    public function import(string $filePath, array $options = []): array
    {
        return $this->runImport($filePath, true, $options);
    }

    private function runImport(string $filePath, bool $persist, array $options = []): array
    {
        $this->sheetErrors = [];
        $results = $this->initialResults($persist ? 'final' : 'preview');
        $removals = $this->buildRemovalLookups($options);
        $memberOverrides = $this->normalizeMemberOverrides($options['member_overrides'] ?? []);
        $reader = IOFactory::createReaderForFile($filePath);
        if (method_exists($reader, 'setReadDataOnly')) {
            $reader->setReadDataOnly(true);
        }
        $spreadsheet = $reader->load($filePath);

        $members = Member::select('id', 'name', 'phone')->get();
        $memberLookup = [];
        foreach ($members as $member) {
            $memberLookup[$this->memberLookupKey($member->name, $member->phone)] = $member;
        }

        $duplicatePhonesInDb = Member::query()
            ->select('phone', DB::raw('MIN(name) as name'), DB::raw('COUNT(*) as total'))
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->groupBy('phone')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->keyBy('phone')
            ->map(function ($item) {
                return ['name' => $item->name, 'total' => $item->total, 'phone' => $item->phone];
            })
            ->toArray();

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $year = $this->detectYear($sheet->getTitle());
            if (!$year) {
                continue;
            }

            try {
                $parsed = $this->parseSheet($sheet, $year, $memberLookup, $duplicatePhonesInDb, $filePath, $memberOverrides, $members);
                $this->mergePreviewData($results, $parsed, !$persist);

                if ($persist) {
                    DB::transaction(function () use (&$results, $parsed, $removals): void {
                        $this->persistSheet($results, $parsed, $removals);
                    });
                }

                $results['summary']['sheets_processed']++;
            } catch (\App\Exceptions\SheetParseException $e) {
                // Non-critical sheet warnings still surface in preview results.
                $results['errors'][] = "Sheet [{$sheet->getTitle()}]: {$e->getMessage()}";
                break;
            } catch (\Throwable $e) {
                if ($this->isStructuredImportException($e)) {
                    throw $e;
                }
                $this->addSheetError("Sheet [{$sheet->getTitle()}]: Unexpected error: {$e->getMessage()}");
                $this->throwCriticalImport('Spreadsheet could not be processed');
            }
        }

        $results['members_info']['existing_count'] = count($results['members_info']['existing_members']);
        $results['members_info']['new_count'] = count($results['members_info']['new_members']);
        $results['members_info']['total_count'] = count($results['members_info']['all_members']);

        $hasErrors = !empty($results['errors']) || !empty($results['members_info']['error_members']);
        $results['status'] = $hasErrors ? 'warning' : 'success';

        // User-friendly payload keys used by the preview modal renderer.
        $results['overview'] = $this->buildOverview($results);
        $results['members'] = $results['members_info'];
        $results['payments'] = $results['monthlyPayments_info'];
        $results['expenses'] = $this->groupExpensesByMonth($results['expenses_info']);

        // Backward-compat aliases used by older dashboard snippets.
        $results['paymentsInfo'] = [
            'records_count' => $results['monthlyPayments_info']['totals']['records_count'],
            'total_amount' => $results['monthlyPayments_info']['totals']['total_amount'],
        ];
        $results['expensesInfo'] = $results['expenses_info']['totals'];

        return $results;
    }

    private function parseSheet(
        Worksheet $sheet,
        int $year,
        array $memberLookup,
        array $duplicatePhonesInDb,
        string $filePath,
        array $memberOverrides,
        Collection $allMembers
    ): array {
        $rows = $this->sheetRowsAsCollection($sheet);
        [$headerRow, $colMap, $monthCols] = $this->detectHeaders($rows);
        if ($headerRow === null) {
            $this->addSheetError("Sheet [{$sheet->getTitle()}]: Could not find member header row. Please check your spreadsheet format.");
            $this->throwCriticalImport('Spreadsheet could not be processed');
        }

        $totalRowIndex = $this->detectTotalRowIndex($rows, $headerRow, $colMap);
        if ($totalRowIndex >= $rows->count()) {
            $this->addSheetError("Sheet [{$sheet->getTitle()}]: No totals row detected in the spreadsheet. Please check your file format.");
            $this->throwCriticalImport('Spreadsheet could not be processed');
        }

        $expenseOnlyRange = $this->locateExpenseOnlyRange($rows, $colMap);
        $memberRows = $rows->slice($headerRow + 1, max(0, $totalRowIndex - $headerRow - 1));
        $welfareSample = $this->detectWelfarePerMember($memberRows, $colMap);

        // --- Track duplicates in the sheet ---
        $nameRows = [];
        $phoneRows = [];
        $sheetMemberKeys = [];
        foreach ($memberRows as $rowIndex => $row) {
            if (!$this->isMemberRow($row, $colMap)) continue;

            $rowNumber = $rowIndex + 1;
            $override = $memberOverrides[$rowNumber] ?? null;
            $name = $override !== null
                ? $this->normaliseName((string) ($override['name'] ?? ''))
                : $this->normaliseName((string) $row->get($colMap['name'] ?? -1, ''));
            $phone = $override !== null
                ? $this->cleanPhone($override['phone'] ?? null)
                : $this->cleanPhone($row->get($colMap['phone'] ?? -1, ''));

            if ($name) {
                $key = strtolower($name);
                $nameRows[$key][] = $rowIndex + 1;
            }
            if ($phone) {
                $phoneRows[$phone][] = $rowIndex + 1;
            }

            $sheetMemberKeys[$this->memberLookupKey($name, $phone)] = true;
        }

        $fuzzyCandidates = $this->buildFuzzyCandidates($allMembers, $sheetMemberKeys);

        $parsedMembers = [];
        $membersData = [
            'existing_members' => [],
            'new_members' => [],
            'all_members' => [],
            'error_members' => [],
            'members' => [],
            'existing_count' => 0,
            'new_count' => 0,
            'total_count' => 0,
        ];
        $monthlyInfo = $this->initialMonthlyPaymentsInfo();
        $rowErrors = [];
        $failedRows = 0;

        foreach ($memberRows as $rowIndex => $row) {
            if (!$this->isMemberRow($row, $colMap)) continue;

            $rowNumber = $rowIndex + 1;
            $override = $memberOverrides[$rowNumber] ?? null;
            $name = $override !== null
                ? $this->normaliseName((string) ($override['name'] ?? ''))
                : $this->normaliseName((string) $row->get($colMap['name'] ?? -1, ''));
            $phone = $override !== null
                ? $this->cleanPhone($override['phone'] ?? null)
                : $this->cleanPhone($row->get($colMap['phone'] ?? -1, ''));
            $errors = [];
            $conflicts = ['sheet_rows' => [], 'database_member' => null];
            $hasSheetDuplicate = false;
            $hasDbDuplicate = false;
            $hasGeneralError = false;

            // --- Skip expense-only rows ---
            if ($this->isWithinExpenseOnlyRange($rowIndex, $expenseOnlyRange)) {
                $failedRows++;
                $membersData['error_members'][] = [
                    'row' => $rowNumber,
                    'name' => $name,
                    'phone' => $phone,
                    'errors' => ['This row belongs to the expense section and cannot be imported as a member.'],
                ];
                $rowErrors[] = "{$name} row was skipped because it belongs to the expense section.";
                continue;
            }

            // --- Sheet name duplicates ---
            if ($name && count($nameRows[strtolower($name)] ?? []) > 1) {
                $duplicateRows = array_diff($nameRows[strtolower($name)], [$rowNumber]);
                $errors[] = "Name '{$name}' appears multiple times in the sheet (also in rows: " . implode(', ', $duplicateRows) . ").";
                $conflicts['sheet_rows'] = array_merge($conflicts['sheet_rows'], $duplicateRows);
                $hasSheetDuplicate = true;
            }

            // --- Missing phone ---
            if (!$phone) {
                $errors[] = "Phone number is missing for '{$name}'.";
                $hasGeneralError = true;
            }

            // --- Sheet phone duplicates ---
            if ($phone && count($phoneRows[$phone] ?? []) > 1) {
                $duplicateRows = array_diff($phoneRows[$phone], [$rowNumber]);
                $errors[] = "Phone {$phone} is duplicated in the sheet (also in rows: " . implode(', ', $duplicateRows) . ").";
                $conflicts['sheet_rows'] = array_merge($conflicts['sheet_rows'], $duplicateRows);
                $hasSheetDuplicate = true;
            }

            // --- Database phone duplicates ---
            if ($phone && isset($duplicatePhonesInDb[$phone])) {
                $existingMember = $duplicatePhonesInDb[$phone]; // ['name' => ..., 'total' => ...]
                $existingName = $existingMember['name'] ?? 'Unknown';
                if (strtolower($existingName) !== strtolower($name)) {
                    $errors[] = "Phone {$phone} exists in database (sheet: '{$name}', database: '{$existingName}').";
                } else {
                    $errors[] = "Phone {$phone} already exists in database as: '{$existingName}'";
                }
                $conflicts['database_member'] = [
                    'name' => $existingName,
                    'phone' => $existingMember['phone'] ?? $phone,
                ];
                $hasDbDuplicate = true;
            }

            $member = $memberLookup[$this->memberLookupKey($name, $phone)] ?? null;
            $status = $member ? 'existing' : 'new';
            $possibleMatch = $status === 'new'
                ? $this->findPossibleMatch($name, $phone, $fuzzyCandidates)
                : null;

            // --- Monthly payments ---
            $payments = [];
            $monthsContributed = [];
            foreach ($monthCols as $monthNumber => $colIndex) {
                $amount = $this->toFloat($row->get($colIndex, 0));
                if ($amount <= 0) continue;

                $payments[] = ['month' => $monthNumber, 'amount' => $amount];
                $monthsContributed[] = $monthNumber;
                $monthName = Payment::MONTHS[$monthNumber] ?? (string) $monthNumber;

                $monthlyInfo['months'][$monthName]['payments_count']++;
                $monthlyInfo['months'][$monthName]['total_amount'] += $amount;
                $monthlyInfo['months'][$monthName]['items'][] = [
                    'row' => $rowNumber,
                    'name' => $name,
                    'phone' => $phone,
                    'amount' => $amount,
                ];
                $monthlyInfo['totals']['records_count']++;
                $monthlyInfo['totals']['total_amount'] += $amount;
            }

            // --- Build member record ---
            $memberRecord = [
                'row' => $rowNumber,
                'name' => $name,
                'phone' => $phone,
                'status' => $status,
                'possible_match' => $possibleMatch,
                'error_type' => $this->resolveErrorType($hasSheetDuplicate, $hasDbDuplicate, $hasGeneralError || !empty($errors)),
                'contributions_brought_forward' => $this->toFloat($row->get($colMap['bf'] ?? -1, 0)),
                'contributions_carried_forward' => $this->toFloat($row->get($colMap['cf'] ?? -1, 0)),
                'total_welfare' => $this->toFloat($row->get($colMap['welfare'] ?? -1, 0)),
                'total_contributions' => $this->toFloat($row->get($colMap['cf'] ?? -1, 0)),
                'total_investment' => $this->toFloat($row->get($colMap['invest'] ?? -1, 0)),
                'months_contributed' => $monthsContributed,
                'errors' => $errors,
                'conflicts' => $conflicts,
            ];

            $membersData['members'][] = $memberRecord;
            $membersData['all_members'][] = [
                'row' => $rowNumber,
                'name' => $name,
                'phone' => $phone,
                'status' => $status,
            ];

            if ($status === 'existing') {
                $membersData['existing_members'][] = $membersData['all_members'][array_key_last($membersData['all_members'])];
            } else {
                $membersData['new_members'][] = $membersData['all_members'][array_key_last($membersData['all_members'])];
            }

            if (!empty($errors)) {
                $failedRows++;
                $membersData['error_members'][] = [
                    'row' => $rowNumber,
                    'name' => $name,
                    'phone' => $phone,
                    'error_type' => $this->resolveErrorType($hasSheetDuplicate, $hasDbDuplicate, $hasGeneralError || !empty($errors)),
                    'errors' => $errors,
                    'conflicts' => $conflicts,
                ];
                foreach ($errors as $error) {
                    $rowErrors[] = $error;
                }
            }

            $parsedMembers[] = [
                'row' => $rowNumber,
                'name' => $name,
                'phone' => $phone,
                'matched_member_id' => $member?->id,
                'status' => $status,
                'possible_match' => $possibleMatch,
                'error_type' => $this->resolveErrorType($hasSheetDuplicate, $hasDbDuplicate, $hasGeneralError || !empty($errors)),
                'errors' => $errors,
                'conflicts' => $conflicts,
                'financial' => [
                    'contributions_brought_forward' => $this->toFloat($row->get($colMap['bf'] ?? -1, 0)),
                    'contributions_carried_forward' => $this->toFloat($row->get($colMap['cf'] ?? -1, 0)),
                    'total_welfare' => $this->toFloat($row->get($colMap['welfare'] ?? -1, 0)),
                    'development' => $this->toFloat($row->get($colMap['dev'] ?? -1, 0)),
                    'welfare_owing' => $this->resolveOwing($row, $colMap),
                    'total_investment' => $this->toFloat($row->get($colMap['invest'] ?? -1, 0)),
                    'pct_share' => $this->toFloat($row->get($colMap['pct'] ?? -1, 0)),
                ],
                'payments' => $payments,
            ];
        }

        [$expensesRows, $bankClosing, $expenseErrors] = $this->parseBottomRows($rows, $totalRowIndex, $monthCols, $colMap);
        $rowErrors = array_merge($rowErrors, $expenseErrors);
        $totalsFromRow = $this->extractTotalsFromRow($rows->get($totalRowIndex), $colMap, $monthCols);

        $expensesInfo = [
            'rows' => $expensesRows,
            'totals' => [
                'records_count' => count($expensesRows),
                'total_amount' => (float) array_sum(array_column($expensesRows, 'amount')),
            ],
        ];

        return [
            'year' => $year,
            'sheet_name' => $sheet->getTitle(),
            'welfare_per_member' => $welfareSample,
            'members' => $parsedMembers,
            'members_info' => $membersData,
            'monthlyPayments_info' => $monthlyInfo,
            'expenses_info' => $expensesInfo,
            'sheet_info' => [
                ['label' => 'Sheet Name', 'value' => $sheet->getTitle(), 'full' => true],
                ['label' => 'File Path', 'value' => $filePath, 'full' => true, 'type' => 'copy'],
                ['label' => 'Year', 'value' => $year],
                ['label' => 'Header Row', 'value' => $headerRow + 1],
                ['label' => 'Total Row', 'value' => $totalRowIndex + 1],
                ['label' => 'Total Members', 'value' => count($membersData['members'])],
                ['label' => 'Total Contributions', 'value' => $totalsFromRow['total_contributions'], 'type' => 'currency'],
                ['label' => 'Total Welfare', 'value' => $totalsFromRow['total_welfare'], 'type' => 'currency'],
                ['label' => 'Total Payments', 'value' => $totalsFromRow['total_payments'], 'type' => 'currency'],
                ['label' => 'Total Investments', 'value' => $totalsFromRow['total_investments'], 'type' => 'currency'],
                ['label' => 'Months Detected', 'value' => array_keys($monthCols), 'type' => 'count'],
            ],
            'bank_closing' => $bankClosing,
            'failed_rows' => $failedRows,
            'errors' => $rowErrors,
        ];
    }

    private function persistSheet(array &$results, array $parsed, array $removals): void
    {
        $financialYear = FinancialYear::updateOrCreate(
            ['year' => $parsed['year']],
            ['sheet_name' => $parsed['sheet_name'], 'welfare_per_member' => $parsed['welfare_per_member']]
        );

        if (!FinancialYear::where('is_current', true)->exists()) {
            $financialYear->update(['is_current' => true]);
        }

        foreach ($parsed['members'] as $memberRow) {
            if (isset($removals['members'][$this->memberRemovalKey($memberRow)])) {
                continue;
            }

            if (!empty($memberRow['errors'])) {
                $results['summary']['failed_rows']++;
                continue;
            }

            $member = null;
            if ($memberRow['matched_member_id']) {
                $member = Member::find($memberRow['matched_member_id']);
                if ($member) {
                    $results['summary']['members_updated']++;
                }
            }

            if (!$member) {
                $member = Member::create([
                    'name' => $memberRow['name'],
                    'phone' => $memberRow['phone'],
                    'joined_year' => $parsed['year'],
                    'is_active' => true,
                ]);
                $results['summary']['members_created']++;
            }

            MemberFinancial::updateOrCreate(
                ['member_id' => $member->id, 'financial_year_id' => $financialYear->id],
                $memberRow['financial'] + ['notes' => null]
            );

            Payment::where('member_id', $member->id)
                ->where('financial_year_id', $financialYear->id)
                ->delete();

            foreach ($memberRow['payments'] as $payment) {
                if (isset($removals['payments'][$this->paymentRemovalKey($memberRow, $payment)])) {
                    continue;
                }

                Payment::create([
                    'member_id' => $member->id,
                    'financial_year_id' => $financialYear->id,
                    'month' => $payment['month'],
                    'amount' => $payment['amount'],
                    'payment_type' => 'contribution',
                ]);
                $results['summary']['payments_created']++;
            }
        }

        Expense::where('financial_year_id', $financialYear->id)->delete();
        BankBalance::where('financial_year_id', $financialYear->id)->delete();

        foreach ($parsed['expenses_info']['rows'] as $expense) {
            if (isset($removals['expenses'][$this->expenseRemovalKey($expense)])) {
                continue;
            }

            ExpenseCategory::findOrImport($expense['category']);
            Expense::create([
                'financial_year_id' => $financialYear->id,
                'month' => $expense['month'],
                'category' => $expense['category'],
                'amount' => abs($expense['amount']),
            ]);
            $results['summary']['expenses_created']++;
        }

        foreach ($parsed['bank_closing'] as $month => $closing) {
            $opening = $parsed['bank_closing'][$month - 1] ?? 0;
            BankBalance::updateOrCreate(
                ['financial_year_id' => $financialYear->id, 'month' => $month],
                ['opening_balance' => $opening, 'closing_balance' => $closing]
            );
        }
    }

    private function mergePreviewData(array &$results, array $parsed, bool $includeFailedRows): void
    {
        if ($includeFailedRows) {
            $results['summary']['failed_rows'] += $parsed['failed_rows'];
        }

        $results['sheet_info'] = $parsed['sheet_info'];
        $results['errors'] = array_merge($results['errors'], $parsed['errors']);

        $results['members_info']['existing_members'] = array_merge(
            $results['members_info']['existing_members'],
            $parsed['members_info']['existing_members']
        );
        $results['members_info']['new_members'] = array_merge(
            $results['members_info']['new_members'],
            $parsed['members_info']['new_members']
        );
        $results['members_info']['all_members'] = array_merge(
            $results['members_info']['all_members'],
            $parsed['members_info']['all_members']
        );
        $results['members_info']['error_members'] = array_merge(
            $results['members_info']['error_members'],
            $parsed['members_info']['error_members']
        );
        $results['members_info']['members'] = array_merge(
            $results['members_info']['members'],
            $parsed['members_info']['members']
        );

        foreach ($parsed['monthlyPayments_info']['months'] as $monthName => $monthData) {
            $target = &$results['monthlyPayments_info']['months'][$monthName];
            $target['payments_count'] += $monthData['payments_count'];
            $target['total_amount'] += $monthData['total_amount'];
            $target['items'] = array_merge($target['items'], $monthData['items']);
        }
        $results['monthlyPayments_info']['totals']['records_count'] += $parsed['monthlyPayments_info']['totals']['records_count'];
        $results['monthlyPayments_info']['totals']['total_amount'] += $parsed['monthlyPayments_info']['totals']['total_amount'];

        $results['expenses_info']['rows'] = array_merge($results['expenses_info']['rows'], $parsed['expenses_info']['rows']);
        $results['expenses_info']['totals']['records_count'] += $parsed['expenses_info']['totals']['records_count'];
        $results['expenses_info']['totals']['total_amount'] += $parsed['expenses_info']['totals']['total_amount'];
    }

    private function initialResults(string $mode): array
    {
        return [
            'status' => 'success',
            'mode' => $mode,
            'summary' => [
                'sheets_processed' => 0,
                'members_created' => 0,
                'members_updated' => 0,
                'payments_created' => 0,
                'expenses_created' => 0,
                'failed_rows' => 0,
            ],
            'sheet_info' => [],
            'members_info' => [
                'existing_members' => [],
                'new_members' => [],
                'all_members' => [],
                'error_members' => [],
                'members' => [],
                'existing_count' => 0,
                'new_count' => 0,
                'total_count' => 0,
            ],
            'monthlyPayments_info' => $this->initialMonthlyPaymentsInfo(),
            'expenses_info' => [
                'rows' => [],
                'totals' => ['records_count' => 0, 'total_amount' => 0.0],
            ],
            'errors' => [],
        ];
    }

    private function buildOverview(array $results): array
    {
        $sheetMembers = 0;
        $sheetContributions = 0.0;
        $sheetWelfare = 0.0;
        $sheetPayments = 0.0;
        $sheetInvestments = 0.0;

        foreach ($results['sheet_info'] as $sheet) {
            $sheetMembers += (int) ($sheet['total_members'] ?? 0);
            $sheetContributions += (float) ($sheet['total_contributions'] ?? 0);
            $sheetWelfare += (float) ($sheet['total_welfare'] ?? 0);
            $sheetPayments += (float) ($sheet['total_payments'] ?? 0);
            $sheetInvestments += (float) ($sheet['total_investments'] ?? 0);
        }

        if ($sheetContributions <= 0.0) {
            $sheetContributions = (float) array_sum(array_map(
                fn(array $member) => (float) ($member['total_contributions'] ?? 0),
                $results['members_info']['members'] ?? []
            ));
        }

        if ($sheetWelfare <= 0.0) {
            $sheetWelfare = (float) array_sum(array_map(
                fn(array $member) => (float) ($member['total_welfare'] ?? 0),
                $results['members_info']['members'] ?? []
            ));
        }

        if ($sheetInvestments <= 0.0) {
            $sheetInvestments = (float) array_sum(array_map(
                fn(array $member) => (float) ($member['total_investments'] ?? 0),
                $results['members_info']['members'] ?? []
            ));
        }

        if ($sheetPayments <= 0.0) {
            $sheetPayments = (float) ($results['monthlyPayments_info']['totals']['total_amount'] ?? 0.0);
        }

        return [
            'total_members' => $sheetMembers > 0 ? $sheetMembers : (int) ($results['members_info']['total_count'] ?? 0),
            'total_contributions' => $sheetContributions,
            'total_welfare' => $sheetWelfare,
            'total_expenses' => (float) ($results['expenses_info']['totals']['total_amount'] ?? 0.0),
            'total_payments' => $sheetPayments,
            'total_investments' => $sheetInvestments
        ];
    }

    private function groupExpensesByMonth(array $expensesInfo): array
    {
        $months = [];
        foreach (Payment::MONTHS as $monthName) {
            $months[$monthName] = [
                'expenses_count' => 0,
                'total_amount' => 0.0,
                'items' => [],
            ];
        }

        foreach (($expensesInfo['rows'] ?? []) as $row) {
            $monthName = $row['month_name'] ?? (Payment::MONTHS[(int) ($row['month'] ?? 0)] ?? 'Unknown');
            if (!isset($months[$monthName])) {
                $months[$monthName] = ['expenses_count' => 0, 'total_amount' => 0.0, 'items' => []];
            }

            $amount = (float) ($row['amount'] ?? 0);
            $months[$monthName]['expenses_count']++;
            $months[$monthName]['total_amount'] += $amount;
            $months[$monthName]['items'][] = $row;
        }

        return [
            'months' => $months,
            'totals' => $expensesInfo['totals'] ?? ['records_count' => 0, 'total_amount' => 0.0],
            'rows' => $expensesInfo['rows'] ?? [],
        ];
    }

    private function initialMonthlyPaymentsInfo(): array
    {
        $months = [];
        foreach (Payment::MONTHS as $name) {
            $months[$name] = ['payments_count' => 0, 'total_amount' => 0.0, 'items' => []];
        }

        return [
            'months' => $months,
            'totals' => ['records_count' => 0, 'total_amount' => 0.0],
        ];
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

    private function detectHeaders(Collection $rows): array
    {
        foreach ($rows as $rowIndex => $row) {
            $upper = $row->map(fn($value) => strtoupper(trim((string) $value)))->values();
            $joined = $upper->implode(' ');
            if (!str_contains($joined, 'MEMBER')) {
                continue;
            }

            $colMap = [];
            $monthCols = [];
            foreach ($upper as $colIndex => $cell) {
                if ($cell === 'NO' || $cell === 'NO.') {
                    $colMap['no'] = $colIndex;
                }
                if (str_contains($cell, 'MEMBERS NAME') || str_contains($cell, 'MEMBER NAME')) {
                    $colMap['name'] = $colIndex;
                }
                if (str_contains($cell, 'TELEPHONE') || str_contains($cell, 'PHONE')) {
                    $colMap['phone'] = $colIndex;
                }
                if ((str_contains($cell, 'B/F') || str_contains($cell, 'BROUGHT')) && !isset($colMap['bf'])) {
                    $colMap['bf'] = $colIndex;
                }
                if ((str_contains($cell, 'C/F') || str_contains($cell, 'CARRIED')) && !isset($colMap['cf'])) {
                    $colMap['cf'] = $colIndex;
                }
                if ($cell === 'TOTAL CONTRIBUTIONS' && !isset($colMap['cf'])) {
                    $colMap['cf'] = $colIndex;
                }
                if (str_contains($cell, 'TOTAL WELFARE')) {
                    $colMap['welfare'] = $colIndex;
                }
                if ($cell === 'DEV.' || $cell === 'DEV' || str_contains($cell, 'DEVELOPMENT')) {
                    $colMap['dev'] = $colIndex;
                }
                if (str_contains($cell, 'OWING')) {
                    $colMap['owing'] = $colIndex;
                }
                if (str_contains($cell, 'INVESTMENT') && !str_contains($cell, 'WITHDRAWAL')) {
                    $colMap['invest'] = $colIndex;
                }
                if (str_contains($cell, '%')) {
                    $colMap['pct'] = $colIndex;
                }

                $month = $this->cellToMonth($cell);
                if ($month !== null) {
                    $monthCols[$month] = $colIndex;
                }
            }

            if (!isset($colMap['name']) || count($monthCols) < 1) {
                continue;
            }

            return [$rowIndex, $colMap, $monthCols];
        }

        return [null, [], []];
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

    private function detectTotalRowIndex(Collection $rows, int $headerRow, array $colMap): int
    {
        foreach ($rows->slice($headerRow + 1) as $rowIndex => $row) {
            $nameCell = strtoupper(trim((string) $row->get($colMap['name'] ?? -1, '')));
            if ($nameCell !== '' && preg_match(self::TOTAL_ROW_PATTERN, $nameCell)) {
                return $rowIndex;
            }

            $joined = $row->map(fn($v) => strtoupper(trim((string) $v)))->implode(' ');
            if ($joined !== '' && preg_match(self::TOTAL_ROW_PATTERN, $joined)) {
                return $rowIndex;
            }
        }

        return $rows->count();
    }

    private function parseBottomRows(Collection $rows, int $totalRowIndex, array $monthCols, array $colMap): array
    {
        $expenses = [];
        $errors = [];
        $bankClosing = [];
        $seen = [];

        $expenseOnlyRange = $this->locateExpenseOnlyRange($rows, $colMap);
        if ($expenseOnlyRange !== null) {
            $bankClosing = $this->extractBankClosingFromRow($rows->get($expenseOnlyRange['start']), $monthCols);
            $this->extractExpensesFromRange(
                $rows,
                $expenseOnlyRange['start'] + 1,
                $expenseOnlyRange['end'] - 1,
                $monthCols,
                $colMap,
                $expenses,
                $seen
            );
            return [$expenses, $bankClosing, $errors];
        }

        $marker = $this->locateBankBalanceMarker($rows);
        if ($marker !== null) {
            $bankClosing = $this->extractBankClosingFromRow($rows->get($marker['row']), $monthCols);
        }

        $scanStart = max(0, $totalRowIndex + 1);
        $lastRow = max(0, $rows->count() - 1);

        // Legacy layout (2022-2023): expense rows are between TOTAL and BANK BAL.
        if ($marker !== null && $marker['row'] > $scanStart) {
            $this->extractExpensesFromRange(
                $rows,
                $scanStart,
                $marker['row'] - 1,
                $monthCols,
                $colMap,
                $expenses,
                $seen
            );
        }

        // Newer layout (2024-2026): expense rows start right after BANK BAL row.
        $afterMarkerStart = $marker !== null ? $marker['row'] + 1 : $scanStart;
        $this->extractExpensesFromRange(
            $rows,
            $afterMarkerStart,
            $lastRow,
            $monthCols,
            $colMap,
            $expenses,
            $seen
        );

        return [$expenses, $bankClosing, $errors];
    }

    private function locateExpenseOnlyRange(Collection $rows, array $colMap): ?array
    {
        $start = null;
        $end = null;

        foreach ($rows as $rowIndex => $row) {
            $nameLabel = $this->normalizeMarkerText((string) $row->get($colMap['name'] ?? -1, ''));
            $bfLabel = $this->normalizeMarkerText((string) $row->get($colMap['bf'] ?? -1, ''));
            $label = trim($nameLabel . ' ' . $bfLabel);
            if ($label === '') {
                continue;
            }

            if ($start === null && $this->isExpenseRangeStartMarker($label)) {
                $start = $rowIndex;
                continue;
            }

            if ($start !== null && $this->isExpenseRangeEndMarker($label)) {
                $end = $rowIndex;
                break;
            }
        }

        if ($start === null || $end === null || $end <= $start) {
            return null;
        }

        return ['start' => $start, 'end' => $end];
    }

    private function locateBankBalanceMarker(Collection $rows): ?array
    {
        foreach ($rows as $rowIndex => $row) {
            foreach ($row as $colIndex => $cell) {
                $normalized = $this->normalizeMarkerText((string) $cell);
                if ($normalized === '') {
                    continue;
                }

                foreach (self::BANK_BAL_MARKERS as $marker) {
                    if (str_contains($normalized, $this->normalizeMarkerText($marker))) {
                        return ['row' => $rowIndex, 'col' => $colIndex];
                    }
                }

                if (preg_match(self::BANK_BAL_PATTERN, $normalized)) {
                    return ['row' => $rowIndex, 'col' => $colIndex];
                }
            }
        }

        return null;
    }

    private function extractBankClosingFromRow($row, array $monthCols): array
    {
        $closing = [];
        if (!$row instanceof Collection) {
            return $closing;
        }

        foreach ($monthCols as $month => $colIndex) {
            $value = $this->toFloat($row->get($colIndex, 0));
            if (abs($value) > 0.01) {
                $closing[$month] = $value;
            }
        }

        return $closing;
    }

    private function extractExpensesFromRange(
        Collection $rows,
        int $startRow,
        int $endRow,
        array $monthCols,
        array $colMap,
        array &$expenses,
        array &$seen
    ): void {
        if ($startRow > $endRow) {
            return;
        }

        $emptyStreak = 0;
        for ($rowIndex = $startRow; $rowIndex <= $endRow; $rowIndex++) {
            $row = $rows->get($rowIndex);
            if (!$row instanceof Collection) {
                continue;
            }

            $nameLabel = $this->normalizeMarkerText((string) $row->get($colMap['name'] ?? -1, ''));
            $bfLabel = $this->normalizeMarkerText((string) $row->get($colMap['bf'] ?? -1, ''));
            $label = trim($nameLabel . ' ' . $bfLabel);
            $hasMonthData = false;
            foreach ($monthCols as $colIndex) {
                if (abs($this->toFloat($row->get($colIndex, 0))) > 0.01) {
                    $hasMonthData = true;
                    break;
                }
            }

            // Defensive stop: if we hit several fully blank rows in this region, the expense table likely ended.
            if ($label === '' && !$hasMonthData) {
                $emptyStreak++;
                if ($emptyStreak >= 3) {
                    break;
                }
                continue;
            }
            $emptyStreak = 0;

            if (preg_match(self::TOTAL_ROW_PATTERN, $label)) {
                break;
            }

            if (preg_match(self::BANK_BAL_PATTERN, $label)) {
                continue;
            }

            $category = $this->matchExpenseCategory(strtoupper($label));
            if (!$category) {
                continue;
            }

            foreach ($monthCols as $month => $colIndex) {
                $value = $this->toFloat($row->get($colIndex, 0));
                if (abs($value) < 0.01) {
                    continue;
                }

                $key = $rowIndex . '|' . $month . '|' . $category;
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;

                $expenses[] = [
                    'row' => $rowIndex + 1,
                    'month' => $month,
                    'month_name' => Payment::MONTHS[$month] ?? (string) $month,
                    'category' => $category,
                    'amount' => abs($value),
                ];
            }
        }
    }

    private function detectWelfarePerMember(Collection $memberRows, array $colMap): float
    {
        if (!isset($colMap['welfare'])) {
            return 0.0;
        }

        $values = $memberRows
            ->map(fn(Collection $row) => $this->toFloat($row->get($colMap['welfare'], 0)))
            ->filter(fn(float $v) => $v > 0)
            ->take(20)
            ->map(fn(float $v) => (int) $v)
            ->values()
            ->all();

        if ($values === []) {
            return 0.0;
        }

        $counts = array_count_values($values);
        arsort($counts);
        return (float) array_key_first($counts);
    }

    private function resolveOwing(Collection $row, array $colMap): float
    {
        return $this->toFloat($row->get($colMap['owing'] ?? -1, 0));
    }

    private function isMemberRow(Collection $row, array $colMap): bool
    {
        $name = trim((string) $row->get($colMap['name'] ?? -1, ''));
        $number = trim((string) $row->get($colMap['no'] ?? -1, ''));

        if ($name === '') {
            return false;
        }

        if ($number === '') {
            return true;
        }

        return is_numeric($number);
    }

    private function matchExpenseCategory(string $label): ?string
    {
        foreach (self::EXPENSE_LABELS as $needle => $category) {
            if (str_contains($label, $needle)) {
                return $category;
            }
        }
        return null;
    }

    private function detectYear(string $title): ?int
    {
        if (preg_match('/\b(20\d{2})\b/', $title, $matches)) {
            return (int) $matches[1];
        }
        return null;
    }

    private function memberLookupKey(string $name, ?string $phone): string
    {
        return strtolower($this->normaliseName($name)) . '|' . ($this->cleanPhone($phone) ?? '');
    }

    private function buildFuzzyCandidates(Collection $members, array $sheetMemberKeys): array
    {
        $candidates = [];
        foreach ($members as $member) {
            $key = $this->memberLookupKey($member->name, $member->phone);
            if (isset($sheetMemberKeys[$key])) {
                continue;
            }

            $candidates[] = [
                'member' => $member,
                'name' => $this->normalizeMatchName($member->name),
                'phone' => $this->normalizeMatchPhone($member->phone),
            ];
        }

        return $candidates;
    }

    private function findPossibleMatch(string $name, ?string $phone, array $candidates): ?array
    {
        if (empty($candidates)) {
            return null;
        }

        $best = null;
        $bestScore = 0.0;

        foreach ($candidates as $candidate) {
            $nameScore = $this->scoreNameMatch($name, $candidate['name'] ?? '');
            $phoneScore = $this->scorePhoneMatch($phone, $candidate['phone'] ?? '');
            $finalScore = ($nameScore * 0.7) + ($phoneScore * 0.3);

            if ($finalScore > $bestScore) {
                $bestScore = $finalScore;
                $best = [
                    'member' => [
                        'id' => $candidate['member']->id,
                        'name' => $candidate['member']->name,
                        'phone' => $candidate['member']->phone,
                    ],
                    'name_match' => round($nameScore),
                    'phone_match' => round($phoneScore),
                    'final_match' => round($finalScore),
                ];
            }
        }

        if ($bestScore < 65) {
            return null;
        }

        return $best;
    }

    private function scoreNameMatch(string $left, string $right): float
    {
        $left = $this->normalizeMatchName($left);
        $right = $this->normalizeMatchName($right);
        if ($left === '' || $right === '') {
            return 0.0;
        }

        similar_text($left, $right, $percent);
        return (float) $percent;
    }

    private function scorePhoneMatch(?string $left, ?string $right): float
    {
        $left = $this->normalizeMatchPhone($left);
        $right = $this->normalizeMatchPhone($right);
        if ($left === '' || $right === '') {
            return 0.0;
        }

        $maxLen = max(strlen($left), strlen($right));
        if ($maxLen === 0) {
            return 0.0;
        }

        $distance = levenshtein($left, $right);
        $score = (1 - ($distance / $maxLen)) * 100;
        return max(0.0, (float) $score);
    }

    // TODO: CHECK FOR FULL STOPS IN THE NAME AND ALSO IF THE PHONE NUMBER IS DUPLICATED ASSIGN A NEW UNIQUE PHONE NUMBER AND WHEN THE NEXT UPLOAD COMES ALONG WITH A NEW PHONE NUMBER LET THE UNIQUE PLACEHOLDER NUMBER BE REPLACED WITH A NEW VALUE
    private function normaliseName(string $name): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $name));
    }

    private function normalizeMatchName(string $name): string
    {
        return strtolower($this->normaliseName($name));
    }

    private function normalizeMatchPhone(?string $value): string
    {
        return (string) ($this->cleanPhone($value) ?? '');
    }

    private function cleanPhone($value): ?string
    {
        $phone = preg_replace('/[^0-9+]/', '', (string) $value);
        return strlen($phone) >= 9 ? $phone : null;
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

    private function buildRemovalLookups(array $options): array
    {
        return [
            'members' => array_fill_keys($options['removed_members'] ?? [], true),
            'payments' => array_fill_keys($options['removed_payments'] ?? [], true),
            'expenses' => array_fill_keys($options['removed_expenses'] ?? [], true),
        ];
    }

    private function normalizeMemberOverrides(array $overrides): array
    {
        $map = [];
        foreach ($overrides as $override) {
            if (!is_array($override)) {
                continue;
            }

            $row = (int) ($override['row'] ?? 0);
            if ($row <= 0) {
                continue;
            }

            $name = $this->normaliseName((string) ($override['name'] ?? ''));
            $phone = $this->cleanPhone($override['phone'] ?? null);

            $map[$row] = [
                'name' => $name,
                'phone' => $phone,
            ];
        }

        return $map;
    }

    private function resolveErrorType(bool $hasSheetDuplicate, bool $hasDbDuplicate, bool $hasGeneralError): ?string
    {
        if ($hasDbDuplicate) {
            return 'db_duplicate';
        }

        if ($hasSheetDuplicate) {
            return 'sheet_duplicate';
        }

        if ($hasGeneralError) {
            return 'general';
        }

        return null;
    }

    private function memberRemovalKey(array $memberRow): string
    {
        return implode('|', [
            $memberRow['row'] ?? '',
            strtolower($this->normaliseName((string) ($memberRow['name'] ?? ''))),
            (string) ($memberRow['phone'] ?? ''),
        ]);
    }

    private function paymentRemovalKey(array $memberRow, array $payment): string
    {
        $monthName = Payment::MONTHS[(int) ($payment['month'] ?? 0)] ?? (string) ($payment['month'] ?? '');
        return implode('|', [
            $memberRow['row'] ?? '',
            strtolower($this->normaliseName((string) ($memberRow['name'] ?? ''))),
            (string) ($memberRow['phone'] ?? ''),
            strtoupper((string) $monthName),
            (string) round((float) ($payment['amount'] ?? 0), 2),
        ]);
    }

    private function expenseRemovalKey(array $expense): string
    {
        return implode('|', [
            $expense['row'] ?? '',
            strtolower((string) ($expense['category'] ?? '')),
            (string) ($expense['month'] ?? ''),
            (string) round((float) ($expense['amount'] ?? 0), 2),
        ]);
    }

    private function isExpenseRangeStartMarker(string $label): bool
    {
        $label = $this->normalizeMarkerText($label);
        return str_contains($label, 'bank balance c/f') || str_contains($label, 'bank balancec/f');
    }

    private function isExpenseRangeEndMarker(string $label): bool
    {
        $label = $this->normalizeMarkerText($label);
        if (!str_contains($label, 'bank bal')) {
            return false;
        }
        return !str_contains($label, 'balance c/f') && !str_contains($label, 'balancec/f');
    }

    private function isWithinExpenseOnlyRange(int $rowIndex, ?array $range): bool
    {
        if ($range === null) {
            return false;
        }
        return $rowIndex > $range['start'] && $rowIndex < $range['end'];
    }

    private function normalizeMarkerText(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/\s+/', ' ', $value);
        return trim((string) $value);
    }

    private function extractTotalsFromRow($row, array $colMap, array $monthCols): array
    {
        if (!$row instanceof Collection) {
            return [
                'total_contributions' => 0.0,
                'total_welfare' => 0.0,
                'total_payments' => 0.0,
                'total_investments' => 0.0
            ];
        }

        $payments = 0.0;
        foreach ($monthCols as $colIndex) {
            $payments += $this->toFloat($row->get($colIndex, 0));
        }

        $contributions = $this->toFloat($row->get($colMap['cf'] ?? -1, 0));
        $welfare = $this->toFloat($row->get($colMap['welfare'] ?? -1, 0));
        $investment = $this->toFloat($row->get($colMap['invest'] ?? -1, 0));

        return [
            'total_contributions' => $contributions,
            'total_welfare' => $welfare,
            'total_payments' => $payments,
            'total_investments' => $investment
        ];
    }

    private function addSheetError(string $message): void
    {
        $this->sheetErrors[] = $message;
    }

    private function throwCriticalImport(string $message): void
    {
        $payload = [
            'status' => 'error',
            'message' => $message,
            'errors' => $this->sheetErrors,
        ];

        throw new \Exception(json_encode($payload));
    }

    private function isStructuredImportException(\Throwable $e): bool
    {
        $decoded = json_decode($e->getMessage(), true);
        return is_array($decoded)
            && ($decoded['status'] ?? null) === 'error'
            && array_key_exists('errors', $decoded);
    }
}
