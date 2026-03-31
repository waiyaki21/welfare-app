<?php

namespace App\Http\Controllers;

use App\Models\Expenditure;
use App\Models\FinancialYear;
use Illuminate\Http\Request;

class ExpenditureController extends Controller
{
    public function index(Request $request)
    {
        $years = FinancialYear::orderByDesc('year')->pluck('year');
        $selectedYear = (int) $request->get('year', $years->first() ?? date('Y'));
        $fy = FinancialYear::where('year', $selectedYear)->first();

        $expenditures = Expenditure::with('financialYear')
            ->when($fy, fn ($q) => $q->where('financial_year_id', $fy->id))
            ->groupedByNarration()
            ->get();

        $groupedByNarration = $expenditures
            ->groupBy(fn ($row) => $row->narration ?: 'Unspecified')
            ->sortKeys();
        $narrationSummaries = $groupedByNarration->map(function ($rows, $narration) {
            $tableRows = $rows->map(function ($item) {
                $actions = '<div class="flex items-center gap-1">'
                    . '<a href="' . route('expenditures.show', $item) . '" class="btn btn-ghost btn-xs">View</a>'
                    . '<a href="' . route('expenditures.edit', $item) . '" class="btn btn-ghost btn-xs">Edit</a>'
                    . '<form method="POST" action="' . route('expenditures.destroy', $item) . '" onsubmit="return confirm(\'Delete this expenditure?\')">'
                    . csrf_field()
                    . method_field('DELETE')
                    . '<button type="submit" class="btn btn-ghost btn-xs" style="color:var(--rust)">Delete</button>'
                    . '</form>'
                    . '</div>';

                return [
                    'name' => e($item->name),
                    'amount' => number_format($item->amount, 2),
                    'actions' => $actions,
                    '__values' => [
                        'name' => $item->name,
                        'amount' => $item->amount,
                        'actions' => '',
                    ],
                ];
            });

            return [
                'narration' => $narration,
                'total' => (float) $rows->sum('amount'),
                'count' => $rows->count(),
                'items' => $rows,
                'tableRows' => $tableRows,
            ];
        })->values();

        $yearTotal = (float) $expenditures->sum('amount');

        return view('expenditures.index', compact(
            'expenditures',
            'years',
            'selectedYear',
            'fy',
            'narrationSummaries',
            'yearTotal'
        ));
    }

    public function show(Expenditure $expenditure)
    {
        $groupRows = Expenditure::where('financial_year_id', $expenditure->financial_year_id)
            ->when($expenditure->narration, function ($query) use ($expenditure) {
                $query->where('narration', $expenditure->narration);
            }, function ($query) {
                $query->whereNull('narration');
            })
            ->orderBy('name')
            ->get();

        $total = (float) $groupRows->sum('amount');
        return view('expenditures.show', compact('expenditure', 'groupRows', 'total'));
    }

    public function create(Request $request)
    {
        $years = FinancialYear::orderByDesc('year')->get(['id', 'year']);
        $selectedYear = (int) $request->get('year', $years->first()->year ?? date('Y'));
        return view('expenditures.create', compact('years', 'selectedYear'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'financial_year_id' => 'required|exists:financial_years,id',
            'narration' => 'nullable|string|max:255',
            'name' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
        ]);

        $data['name'] = trim((string) preg_replace('/\s+/', ' ', $data['name']));
        $data['narration'] = trim((string) ($data['narration'] ?? '')) ?: null;
        Expenditure::create($data);

        $year = FinancialYear::find($data['financial_year_id'])?->year;
        return redirect()->route('expenditures.index', ['year' => $year])->with('success', 'Expenditure created.');
    }

    public function edit(Expenditure $expenditure)
    {
        $years = FinancialYear::orderByDesc('year')->get(['id', 'year']);
        return view('expenditures.edit', compact('expenditure', 'years'));
    }

    public function update(Request $request, Expenditure $expenditure)
    {
        $data = $request->validate([
            'financial_year_id' => 'required|exists:financial_years,id',
            'narration' => 'nullable|string|max:255',
            'name' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
        ]);

        $data['name'] = trim((string) preg_replace('/\s+/', ' ', $data['name']));
        $data['narration'] = trim((string) ($data['narration'] ?? '')) ?: null;
        $expenditure->update($data);

        $year = FinancialYear::find($data['financial_year_id'])?->year;
        return redirect()->route('expenditures.index', ['year' => $year])->with('success', 'Expenditure updated.');
    }

    public function destroy(Expenditure $expenditure)
    {
        $year = $expenditure->financialYear?->year;
        $expenditure->delete();
        return redirect()->route('expenditures.index', ['year' => $year])->with('success', 'Expenditure deleted.');
    }

    // Month-based normalization removed: expenditures now group by narration only.
}
