<?php

namespace App\Http\Controllers;

use App\Models\ExpenseCategory;
use App\Models\Expense;
use Illuminate\Http\Request;

class ExpenseCategoryController extends Controller
{
    public function index()
    {
        $categories = ExpenseCategory::withCount('expenses')
            ->orderBy('name')
            ->get();

        return view('expense-categories.index', compact('categories'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'  => 'required|string|max:100',
            'color' => 'nullable|string|max:7',
        ]);

        $slug = ExpenseCategory::slugify($data['name']);

        // If slug already exists just update the name/colour
        $cat = ExpenseCategory::updateOrCreate(
            ['slug' => $slug],
            [
                'name'      => $data['name'],
                'color'     => $data['color'] ?? '#fef3c7',
                'is_active' => true,
            ]
        );

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'category' => $cat]);
        }

        return redirect()->route('expenses.index')
            ->with('success', "Category \"{$cat->name}\" saved.");
    }

    public function update(Request $request, ExpenseCategory $expenseCategory)
    {
        $data = $request->validate([
            'name'      => 'required|string|max:100',
            'color'     => 'nullable|string|max:7',
            'is_active' => 'nullable|boolean',
        ]);

        $expenseCategory->update([
            'name'      => $data['name'],
            'color'     => $data['color'] ?? $expenseCategory->color,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('expense-categories.index')
            ->with('success', "Category \"{$expenseCategory->name}\" updated.");
    }

    public function destroy(ExpenseCategory $expenseCategory)
    {
        $count = Expense::where('category', $expenseCategory->slug)->count();
        if ($count > 0) {
            return redirect()->route('expense-categories.index')
                ->withErrors(["Cannot delete \"{$expenseCategory->name}\" — it has {$count} expense record(s). Deactivate it instead."]);
        }

        $expenseCategory->delete();
        return redirect()->route('expense-categories.index')
            ->with('success', "Category \"{$expenseCategory->name}\" deleted.");
    }
}
