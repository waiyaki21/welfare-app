<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\BankBalance;
use App\Models\Expenditure;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FinancialYear;
use App\Models\Member;
use App\Models\MemberFinancial;
use App\Models\Payment;
use App\Models\User;
use App\Models\WelfareEvent;
use App\Services\SpreadsheetExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FinancialYearController extends Controller
{
    public function index()
    {
        $years = FinancialYear::withCount('expenditures')
            ->withSum('expenditures', 'amount')
            ->orderByDesc('year')
            ->get()
            ->map(function ($fy) {
            $fy->member_count   = $fy->memberFinancials()->count();
            $fy->total_contrib  = (float) Payment::where('financial_year_id', $fy->id)->sum('amount');
            $fy->total_welfare  = (float) $fy->memberFinancials()->sum('total_welfare');
            $fy->total_invest   = (float) $fy->memberFinancials()->sum('total_investment');
            $fy->total_expenses = (float) Expense::where('financial_year_id', $fy->id)->sum('amount');
            $fy->expenditures_count = (int) ($fy->expenditures_count ?? 0);
            $fy->expenditures_total = (float) ($fy->expenditures_sum_amount ?? 0);
            $fy->surplus_count  = $fy->memberFinancials()->where('welfare_owing', '>=', 0)->count();
            $fy->deficit_count  = $fy->memberFinancials()->where('welfare_owing', '<', 0)->count();
            $fy->payment_count  = Payment::where('financial_year_id', $fy->id)->count();
            return $fy;
        });

        return view('financial-years.index', compact('years'));
    }

    public function show(FinancialYear $financialYear)
    {
        $fy         = $financialYear;
        $financials = $fy->memberFinancials()->with('member')->get();

        $payRows = Payment::where('financial_year_id', $fy->id)
            ->selectRaw('month, SUM(amount) as total')
            ->groupBy('month')->orderBy('month')
            ->pluck('total', 'month')->toArray();

        $monthlyTotals = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthlyTotals[$m] = (float) ($payRows[$m] ?? 0);
        }

        $expensesByCat = Expense::where('financial_year_id', $fy->id)
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->pluck('total', 'category')->toArray();

        $bankBalances   = $fy->bankBalances;
        $topMembers     = $financials->sortByDesc('total_investment')->take(10);
        $deficitMembers = $financials->where('welfare_owing', '<', 0)->sortBy('welfare_owing');

        $stats = [
            'members'    => $financials->count(),
            'contrib'    => array_sum($monthlyTotals),
            'welfare'    => (float) $financials->sum('total_welfare'),
            'invest'     => (float) $financials->sum('total_investment'),
            'expenses'   => array_sum($expensesByCat),
            'surplus'    => $financials->where('welfare_owing', '>=', 0)->count(),
            'deficit'    => $financials->where('welfare_owing', '<', 0)->count(),
            'no_payment' => $financials->count() - Payment::where('financial_year_id', $fy->id)
                ->distinct('member_id')->count('member_id'),
        ];

        return view('financial-years.show', compact(
            'fy',
            'stats',
            'monthlyTotals',
            'expensesByCat',
            'bankBalances',
            'topMembers',
            'deficitMembers',
            'financials'
        ));
    }

    public function edit(FinancialYear $financialYear)
    {
        return view('financial-years.edit', ['fy' => $financialYear]);
    }

    public function update(Request $request, FinancialYear $financialYear)
    {
        $validated = $request->validate([
            'year'               => 'required|integer|min:2000|max:2100|unique:financial_years,year,' . $financialYear->id,
            'sheet_name'         => 'nullable|string|max:100',
            'welfare_per_member' => 'nullable|numeric|min:0',
            'is_current'         => 'nullable|boolean',
        ]);

        if ($request->boolean('is_current')) {
            FinancialYear::where('id', '!=', $financialYear->id)->update(['is_current' => false]);
        }

        $financialYear->update([
            'year'               => $validated['year'],
            'sheet_name'         => $validated['sheet_name'] ?? null,
            'welfare_per_member' => $validated['welfare_per_member'] ?? 0,
            'is_current'         => $request->boolean('is_current'),
        ]);

        return redirect()
            ->route('financial-years.show', $financialYear)
            ->with('success', "Financial year {$financialYear->year} updated.");
    }

    public function destroy(FinancialYear $financialYear)
    {
        $year = $financialYear->year;

        DB::transaction(function () use ($financialYear) {
            Payment::where('financial_year_id', $financialYear->id)->delete();
            Expense::where('financial_year_id', $financialYear->id)->delete();
            BankBalance::where('financial_year_id', $financialYear->id)->delete();
            WelfareEvent::where('financial_year_id', $financialYear->id)->delete();
            MemberFinancial::where('financial_year_id', $financialYear->id)->delete();
            $financialYear->delete();
        });

        return redirect()
            ->route('financial-years.index')
            ->with('success', "Financial year {$year} and all its data have been deleted.");
    }

    // ── Reset Database ───────────────────────────────────────────────────────

    public function resetConfirm()
    {
        $counts = [
            'members'          => Member::count(),
            'financial_years'  => FinancialYear::count(),
            'payments'         => Payment::count(),
            'expenses'         => Expense::count(),
            'expenditures'     => Expenditure::count(),
            'member_financials' => MemberFinancial::count(),
            'users'            => User::count(),
            'settings'         => AppSetting::count(),
        ];

        return view('financial-years.reset', compact('counts'));
    }

    public function resetExecute(Request $request)
    {
        $resetUsers = $request->boolean('reset_users');

        $expectedWord = $resetUsers ? 'RESET ALL' : 'RESET';

        $request->validate([
            'confirm_text' => ['required', "in:{$expectedWord}"],
        ], [
            'confirm_text.in' => "You must type {$expectedWord} (all caps) to confirm.",
        ]);

        DB::transaction(function () use ($resetUsers) {

            DB::statement('PRAGMA foreign_keys = OFF');

            Payment::query()->forceDelete();
            Expense::query()->delete();
            BankBalance::query()->delete();
            WelfareEvent::query()->delete();
            MemberFinancial::query()->delete();
            FinancialYear::query()->delete();
            Member::query()->delete();
            ExpenseCategory::query()->delete();
            Expenditure::query()->delete();

            if ($resetUsers) {
                User::query()->delete();
                AppSetting::query()->delete();

                // 🔥 Clear ALL cached settings
                Cache::flush();
            }

            DB::statement('PRAGMA foreign_keys = ON');

            // Reset auto-increment IDs
            $tables = [
                'payments',
                'expenses',
                'bank_balances',
                'welfare_events',
                'member_financials',
                'financial_years',
                'members',
                'expense_categories',
                'expenditures'
            ];

            if ($resetUsers) {
                $tables[] = 'users';
                $tables[] = 'app_settings';
            }

            foreach ($tables as $table) {
                DB::statement("DELETE FROM sqlite_sequence WHERE name = '{$table}'");
            }

            // 🔥 Delete uploaded files
            Storage::deleteDirectory('imports');
        });

        if ($resetUsers) {
            Auth::logout();
            request()->session()->invalidate();
            request()->session()->regenerateToken();

            return redirect()->route('auth.login')
                ->with('success', 'Full reset complete. All data, settings, and files cleared.');
        }

        return redirect()->route('dashboard')
            ->with('success', 'Database and files reset complete.');
    }

    // public function resetExecute(Request $request)
    // {
    //     $resetUsers = $request->boolean('reset_users');

    //     $expectedWord = $resetUsers ? 'RESET ALL' : 'RESET';

    //     $request->validate([
    //         'confirm_text' => ['required', "in:{$expectedWord}"],
    //     ], [
    //         'confirm_text.in' => "You must type {$expectedWord} (all caps) to confirm.",
    //     ]);

    //     DB::transaction(function () use ($resetUsers) {
    //         // Hard delete everything — no soft deletes, no orphans left behind
    //         DB::statement('PRAGMA foreign_keys = OFF');

    //         Payment::query()->forceDelete();
    //         Expense::query()->delete();
    //         BankBalance::query()->delete();
    //         WelfareEvent::query()->delete();
    //         MemberFinancial::query()->delete();
    //         FinancialYear::query()->delete();
    //         Member::query()->delete();
    //         ExpenseCategory::query()->delete();

    //         if ($resetUsers) {
    //             User::query()->delete();
    //             AppSetting::query()->delete();
    //             Cache::flush(); // simplest + safest
    //             Storage::deleteDirectory('imports');
    //         }

    //         DB::statement('PRAGMA foreign_keys = ON');

    //         // Reset all auto-increment counters so IDs start from 1 again
    //         $tables = ['payments','expenses','bank_balances','welfare_events',
    //                    'member_financials','financial_years','members','expense_categories'];
    //         if ($resetUsers) {
    //             $tables[] = 'users';
    //             $tables[] = 'app_settings';
    //         }
    //         foreach ($tables as $table) {
    //             DB::statement("DELETE FROM sqlite_sequence WHERE name = '{$table}'");
    //         }
    //     });

    //     if ($resetUsers) {
    //         Auth::logout();
    //         request()->session()->invalidate();
    //         request()->session()->regenerateToken();
    //         return redirect()->route('auth.login')
    //             ->with('success', 'Full reset complete. All data and user accounts cleared.');
    //     }

    //     return redirect()->route('dashboard')
    //         ->with('success', 'Database reset complete. All association data cleared.');
    // }

    // ── Export ───────────────────────────────────────────────────────────────

    public function export(FinancialYear $financialYear, SpreadsheetExportService $exporter): BinaryFileResponse
    {
        $path     = $exporter->export($financialYear);
        $filename = "Athoni_Welfare_{$financialYear->year}.xlsx";

        return response()->download($path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }
}
