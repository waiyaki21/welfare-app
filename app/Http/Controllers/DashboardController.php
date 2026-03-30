<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\FinancialYear;
use App\Models\Member;
use App\Models\Payment;
use App\Models\Expense;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $years        = FinancialYear::orderByDesc('year')->pluck('year');
        $selectedYear = (int) $request->get('year', $years->first() ?? date('Y'));

        $fy = FinancialYear::where('year', $selectedYear)->first();

        $importStates = [
            'year' => AppSetting::importState('year'),
            'month' => AppSetting::importState('month'),
            'expenditure' => AppSetting::importState('expenditure'),
        ];

        if (!$fy) {
            return view('dashboard.index', [
                'years'           => $years,
                'selectedYear'    => $selectedYear,
                'fy'              => null,
                'stats'           => [],
                'monthlyTotals'   => [],
                'monthlyExpenses' => [],
                'bankBalances'    => collect(),
                'topMembers'      => collect(),
                'deficitMembers'  => collect(),
                'yearOnYear'      => collect(),
                'byCat'           => [],
                'importStates'   => $importStates
            ]);
        }

        $financials      = $fy->memberFinancials()->with('member')->get();
        $totalContrib    = (float) Payment::where('financial_year_id', $fy->id)->sum('amount');
        $totalExpenses   = (float) Expense::where('financial_year_id', $fy->id)->sum('amount');
        $totalWelfare    = (float) $financials->sum('total_welfare');
        $totalInvestment = (float) $financials->sum('total_investment');
        $memberCount     = $financials->count();

        $stats = [
            'members'     => $memberCount,
            'contrib'     => $totalContrib,
            'welfare'     => $totalWelfare,
            'expenses'    => $totalExpenses,
            'investment'  => $totalInvestment,
            'deficit_amt' => abs((float) $financials->where('welfare_owing', '<', 0)->sum('welfare_owing')),
            'surplus_cnt' => $financials->where('welfare_owing', '>=', 0)->count(),
            'deficit_cnt' => $financials->where('welfare_owing', '<', 0)->count(),
            'no_payment'  => $memberCount - Payment::where('financial_year_id', $fy->id)
                ->distinct('member_id')->count('member_id'),
        ];

        $payRows = Payment::where('financial_year_id', $fy->id)
            ->selectRaw('month, SUM(amount) as total')
            ->groupBy('month')->pluck('total', 'month')->toArray();

        $monthlyTotals = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthlyTotals[$m] = (float) ($payRows[$m] ?? 0);
        }

        $expRows = Expense::where('financial_year_id', $fy->id)
            ->selectRaw('month, SUM(amount) as total')
            ->groupBy('month')->pluck('total', 'month')->toArray();

        $monthlyExpenses = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthlyExpenses[$m] = (float) ($expRows[$m] ?? 0);
        }

        $byCat = Expense::where('financial_year_id', $fy->id)
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')->pluck('total', 'category')->toArray();

        $topMembers     = $financials->sortByDesc('total_investment')->take(10);
        $deficitMembers = $financials->where('welfare_owing', '<', 0)->sortBy('welfare_owing')->take(10);
        $bankBalances   = $fy->bankBalances;

        $yearOnYear = FinancialYear::orderBy('year')->get()->map(fn($y) => [
            'year'    => $y->year,
            'contrib' => (float) Payment::where('financial_year_id', $y->id)->sum('amount'),
            'welfare' => (float) $y->memberFinancials()->sum('total_welfare'),
            'members' => $y->memberFinancials()->count(),
        ])->values();

        return view('dashboard.index', compact(
            'years',
            'selectedYear',
            'fy',
            'stats',
            'monthlyTotals',
            'monthlyExpenses',
            'bankBalances',
            'topMembers',
            'deficitMembers',
            'yearOnYear',
            'byCat',
            'importStates'
        ));
    }
}
