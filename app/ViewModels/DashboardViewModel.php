<?php

namespace App\ViewModels;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FinancialYear;
use App\Models\MemberFinancial;
use App\Models\Payment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class DashboardViewModel
{
    public function build(int $selectedYear): array
    {
        $years = Cache::remember(
            'dashboard.years',
            now()->addHour(),
            fn () => FinancialYear::orderByDesc('year')->pluck('year')
        );

        $selectedYear = (int) ($selectedYear ?: ($years->first() ?? now()->year));
        $financialYear = FinancialYear::query()
            ->where('year', $selectedYear)
            ->first(['id', 'year', 'welfare_per_member']);

        if (!$financialYear) {
            return [
                'years' => $years,
                'selectedYear' => $selectedYear,
                'financialYear' => null,
                'statCards' => [],
                'expenseCategoryRows' => [],
                'topMembers' => collect(),
                'deficitMembers' => collect(),
                'monthlyBreakdown' => [],
                'bankBalances' => collect(),
                'chartData' => $this->emptyChartData(),
            ];
        }

        $financialYearId = $financialYear->id;

        $memberAggregates = Cache::remember(
            "dashboard.{$selectedYear}.member-aggregates",
            now()->addHour(),
            fn () => MemberFinancial::query()
                ->where('financial_year_id', $financialYearId)
                ->selectRaw('
                    COUNT(*) as members,
                    COALESCE(SUM(total_welfare), 0) as welfare,
                    COALESCE(SUM(total_investment), 0) as investment,
                    COALESCE(SUM(CASE WHEN welfare_owing < 0 THEN 1 ELSE 0 END), 0) as deficit_cnt,
                    COALESCE(SUM(CASE WHEN welfare_owing >= 0 THEN 1 ELSE 0 END), 0) as surplus_cnt
                ')
                ->first()
        );

        $paymentAggregates = Cache::remember(
            "dashboard.{$selectedYear}.payment-aggregates",
            now()->addHour(),
            fn () => Payment::query()
                ->where('financial_year_id', $financialYearId)
                ->selectRaw('COALESCE(SUM(amount), 0) as total, COUNT(DISTINCT member_id) as active_members')
                ->first()
        );

        $expenseAggregates = Cache::remember(
            "dashboard.{$selectedYear}.expense-aggregates",
            now()->addHour(),
            fn () => Expense::query()
                ->where('financial_year_id', $financialYearId)
                ->selectRaw('COALESCE(SUM(amount), 0) as total')
                ->first()
        );

        $monthlyTotals = Cache::remember(
            "dashboard.{$selectedYear}.monthly-payments",
            now()->addHour(),
            fn () => $this->normalizeMonthlyTotals(
                Payment::query()
                    ->where('financial_year_id', $financialYearId)
                    ->selectRaw('month, SUM(amount) as total')
                    ->groupBy('month')
                    ->pluck('total', 'month')
                    ->toArray()
            )
        );

        $monthlyExpenses = Cache::remember(
            "dashboard.{$selectedYear}.monthly-expenses",
            now()->addHour(),
            fn () => $this->normalizeMonthlyTotals(
                Expense::query()
                    ->where('financial_year_id', $financialYearId)
                    ->selectRaw('month, SUM(amount) as total')
                    ->groupBy('month')
                    ->pluck('total', 'month')
                    ->toArray()
            )
        );

        $categories = Cache::remember(
            'dashboard.expense-categories',
            now()->addHour(),
            fn () => ExpenseCategory::query()->pluck('name', 'slug')->toArray()
        );

        $expenseCategories = Cache::remember(
            "dashboard.{$selectedYear}.expense-categories",
            now()->addHour(),
            fn () => Expense::query()
                ->where('financial_year_id', $financialYearId)
                ->selectRaw('category, SUM(amount) as total')
                ->groupBy('category')
                ->pluck('total', 'category')
                ->toArray()
        );

        $topMembers = Cache::remember(
            "dashboard.{$selectedYear}.top-members",
            now()->addHour(),
            fn () => MemberFinancial::query()
                ->with('member:id,name')
                ->where('financial_year_id', $financialYearId)
                ->orderByDesc('total_investment')
                ->limit(10)
                ->get(['id', 'member_id', 'financial_year_id', 'total_investment', 'total_welfare', 'welfare_owing'])
        );

        $deficitMembers = Cache::remember(
            "dashboard.{$selectedYear}.deficit-members",
            now()->addHour(),
            fn () => MemberFinancial::query()
                ->with('member:id,name')
                ->where('financial_year_id', $financialYearId)
                ->where('welfare_owing', '<', 0)
                ->orderBy('welfare_owing')
                ->limit(10)
                ->get([
                    'id',
                    'member_id',
                    'financial_year_id',
                    'contributions_carried_forward',
                    'total_welfare',
                    'welfare_owing',
                ])
        );

        $bankBalances = Cache::remember(
            "dashboard.{$selectedYear}.bank-balances",
            now()->addHour(),
            fn () => $financialYear->bankBalances()->get(['id', 'financial_year_id', 'month', 'opening_balance', 'closing_balance'])
        );

        $yearOnYear = Cache::remember(
            'dashboard.year-on-year',
            now()->addHour(),
            function (): Collection {
                $years = FinancialYear::query()->orderBy('year')->get(['id', 'year']);
                $paymentTotals = Payment::query()
                    ->selectRaw('financial_year_id, COALESCE(SUM(amount), 0) as total')
                    ->groupBy('financial_year_id')
                    ->pluck('total', 'financial_year_id');
                $memberFinancials = MemberFinancial::query()
                    ->selectRaw('financial_year_id, COUNT(*) as members, COALESCE(SUM(total_welfare), 0) as welfare')
                    ->groupBy('financial_year_id')
                    ->get()
                    ->keyBy('financial_year_id');

                return $years->map(function (FinancialYear $year) use ($paymentTotals, $memberFinancials): array {
                    $financials = $memberFinancials->get($year->id);

                    return [
                        'year' => $year->year,
                        'contrib' => (float) ($paymentTotals[$year->id] ?? 0),
                        'welfare' => (float) ($financials?->welfare ?? 0),
                        'members' => (int) ($financials?->members ?? 0),
                    ];
                });
            }
        );

        $memberCount = (int) ($memberAggregates?->members ?? 0);
        $activeMembers = (int) ($paymentAggregates?->active_members ?? 0);
        $totalContrib = (float) ($paymentAggregates?->total ?? 0);
        $totalExpenses = (float) ($expenseAggregates?->total ?? 0);
        $totalWelfare = (float) ($memberAggregates?->welfare ?? 0);
        $totalInvestment = (float) ($memberAggregates?->investment ?? 0);

        return [
            'years' => $years,
            'selectedYear' => $selectedYear,
            'financialYear' => $financialYear,
            'statCards' => $this->buildStatCards(
                $financialYear,
                $memberCount,
                $activeMembers,
                $totalContrib,
                $totalExpenses,
                $totalWelfare,
                $totalInvestment,
                (int) ($memberAggregates?->deficit_cnt ?? 0),
                (int) ($memberAggregates?->surplus_cnt ?? 0)
            ),
            'expenseCategoryRows' => $this->buildExpenseCategoryRows($expenseCategories, $categories),
            'topMembers' => $topMembers,
            'deficitMembers' => $deficitMembers,
            'monthlyBreakdown' => $this->buildMonthlyBreakdown($monthlyTotals, $monthlyExpenses),
            'bankBalances' => $bankBalances,
            'chartData' => $this->buildChartData($selectedYear, $monthlyTotals, $monthlyExpenses, $yearOnYear, $expenseCategories),
        ];
    }

    private function buildStatCards(
        FinancialYear $financialYear,
        int $memberCount,
        int $activeMembers,
        float $totalContrib,
        float $totalExpenses,
        float $totalWelfare,
        float $totalInvestment,
        int $deficitCount,
        int $surplusCount
    ): array {
        $cards = [
            [
                'label' => 'Members',
                'value' => number_format($memberCount),
                'sub' => "in {$financialYear->year}",
                'variant' => 'dark',
            ],
            [
                'label' => 'Contributions',
                'value' => number_format($totalContrib),
                'sub' => 'KES collected',
                'variant' => 'default',
            ],
            [
                'label' => 'Total Welfare',
                'value' => number_format($totalWelfare),
                'sub' => 'KES disbursed',
                'variant' => 'green',
            ],
            [
                'label' => 'Expenses',
                'value' => number_format($totalExpenses),
                'sub' => 'KES operating costs',
                'variant' => 'default',
            ],
            [
                'label' => 'Investment Pool',
                'value' => number_format($totalInvestment),
                'sub' => 'KES net position',
                'variant' => 'default',
            ],
            [
                'label' => 'Members in Deficit',
                'value' => (string) $deficitCount,
                'sub' => "{$surplusCount} in surplus",
                'variant' => $deficitCount > 0 ? 'red' : 'default',
            ],
            [
                'label' => 'No Payment Yet',
                'value' => (string) max($memberCount - $activeMembers, 0),
                'sub' => 'members inactive',
                'variant' => 'default',
            ],
        ];

        if ($financialYear->welfare_per_member) {
            $cards[] = [
                'label' => 'Welfare/Member',
                'value' => number_format((float) $financialYear->welfare_per_member),
                'sub' => 'KES standard',
                'variant' => 'default',
            ];
        }

        return $cards;
    }

    private function buildExpenseCategoryRows(array $expenseCategories, array $categories): array
    {
        $total = (float) array_sum($expenseCategories);

        return collect($expenseCategories)
            ->map(fn ($amount, $category) => [
                'category' => $categories[$category] ?? ucfirst(str_replace('_', ' ', (string) $category)),
                'amount' => (float) $amount,
                'percentage' => $total > 0 ? round(((float) $amount / $total) * 100, 1) : 0.0,
            ])
            ->sortByDesc('amount')
            ->values()
            ->all();
    }

    private function buildMonthlyBreakdown(array $monthlyTotals, array $monthlyExpenses): array
    {
        $yearContributions = (float) array_sum($monthlyTotals);
        $rows = [];

        foreach (Payment::MONTHS as $month => $monthName) {
            $contributions = (float) ($monthlyTotals[$month] ?? 0);
            $expenses = (float) ($monthlyExpenses[$month] ?? 0);

            $rows[] = [
                'month' => $monthName,
                'contributions' => $contributions,
                'expenses' => $expenses,
                'net' => $contributions - $expenses,
                'share' => $yearContributions > 0 ? round(($contributions / $yearContributions) * 100, 1) : 0.0,
            ];
        }

        return $rows;
    }

    private function buildChartData(
        int $selectedYear,
        array $monthlyTotals,
        array $monthlyExpenses,
        Collection $yearOnYear,
        array $expenseCategories
    ): array {
        return [
            'selectedYear' => $selectedYear,
            'monthlyCollections' => [
                'labels' => array_values(Payment::MONTHS),
                'contributions' => array_values($monthlyTotals),
                'expenses' => array_values($monthlyExpenses),
            ],
            'yearOnYear' => [
                'labels' => $yearOnYear->pluck('year')->values()->all(),
                'contributions' => $yearOnYear->pluck('contrib')->map(fn ($value) => (float) $value)->values()->all(),
                'welfare' => $yearOnYear->pluck('welfare')->map(fn ($value) => (float) $value)->values()->all(),
            ],
            'expenseDistribution' => [
                'labels' => array_map(
                    fn ($category) => ucfirst(str_replace('_', ' ', (string) $category)),
                    array_keys($expenseCategories)
                ),
                'values' => array_map(fn ($value) => (float) $value, array_values($expenseCategories)),
            ],
        ];
    }

    private function normalizeMonthlyTotals(array $rows): array
    {
        $normalized = [];

        foreach (range(1, 12) as $month) {
            $normalized[$month] = (float) ($rows[$month] ?? 0);
        }

        return $normalized;
    }

    private function emptyChartData(): array
    {
        return [
            'selectedYear' => null,
            'monthlyCollections' => [
                'labels' => array_values(Payment::MONTHS),
                'contributions' => array_fill(0, 12, 0),
                'expenses' => array_fill(0, 12, 0),
            ],
            'yearOnYear' => [
                'labels' => [],
                'contributions' => [],
                'welfare' => [],
            ],
            'expenseDistribution' => [
                'labels' => [],
                'values' => [],
            ],
        ];
    }
}
