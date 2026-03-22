<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FinancialYear;
use App\Http\Requests\StoreExpenseRequest;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $years        = FinancialYear::orderByDesc('year')->pluck('year');
        $selectedYear = (int) $request->get('year', $years->first() ?? date('Y'));
        $monthFilter  = (int) $request->get('month', 0);
        $catFilter    = $request->get('category', '');

        $fy = FinancialYear::where('year', $selectedYear)->first();

        $expenses = Expense::with(['financialYear', 'expenseCategory'])
            ->when($fy,          fn ($q) => $q->where('financial_year_id', $fy->id))
            ->when($monthFilter, fn ($q) => $q->where('month', $monthFilter))
            ->when($catFilter,   fn ($q) => $q->where('category', $catFilter))
            ->orderBy('month')->orderBy('category')
            ->paginate(500)   // load all — client-side table handles paging
            ->withQueryString();

        $byCat = $fy
            ? Expense::where('financial_year_id', $fy->id)
                ->selectRaw('category, SUM(amount) as total')
                ->groupBy('category')->pluck('total', 'category')->toArray()
            : [];

        $byMonth = $fy
            ? Expense::where('financial_year_id', $fy->id)
                ->selectRaw('month, SUM(amount) as total')
                ->groupBy('month')->pluck('total', 'month')->toArray()
            : [];

        $yearTotal  = array_sum($byCat);
        $fyAll      = FinancialYear::orderByDesc('year')->get(['id', 'year']);
        $categories = ExpenseCategory::active()->orderBy('name')->get();

        return view('expenses.index', compact(
            'expenses', 'years', 'selectedYear', 'monthFilter', 'catFilter',
            'byCat', 'byMonth', 'yearTotal', 'fyAll', 'fy', 'categories'
        ));
    }

    public function store(StoreExpenseRequest $request)
    {
        // Auto-create category if it doesn't exist yet
        ExpenseCategory::findOrImport($request->category);

        $expense = Expense::create($request->validated());

        return redirect()
            ->route('expenses.index', ['year' => $expense->financialYear->year])
            ->with('success', 'Expense of KES ' . number_format($expense->amount) . ' recorded.');
    }

    public function edit(Expense $expense)
    {
        $fyAll      = FinancialYear::orderByDesc('year')->get(['id', 'year']);
        $categories = ExpenseCategory::active()->orderBy('name')->get();
        return view('expenses.edit', compact('expense', 'fyAll', 'categories'));
    }

    public function update(StoreExpenseRequest $request, Expense $expense)
    {
        ExpenseCategory::findOrImport($request->category);
        $expense->update($request->validated());
        return redirect()
            ->route('expenses.index', ['year' => $expense->financialYear->year])
            ->with('success', 'Expense updated.');
    }

    public function destroy(Expense $expense)
    {
        $year = $expense->financialYear->year;
        $expense->delete();
        return redirect()
            ->route('expenses.index', ['year' => $year])
            ->with('success', 'Expense deleted.');
    }
}
