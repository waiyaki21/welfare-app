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
            ->orderByRaw('month IS NULL, month')
            ->orderBy('name')
            ->get();

        $groupedByMonth = $expenditures->groupBy(fn ($row) => $row->month ?: 0)->sortKeys();
        $monthSummaries = $groupedByMonth->map(function ($rows, $month) {
            $monthInt = (int) $month;

            return [
                'month' => $monthInt,
                'month_name' => $monthInt > 0
                    ? (\App\Models\Payment::MONTHS[$monthInt] ?? (string) $monthInt)
                    : 'Unspecified',
                'total' => (float) $rows->sum('amount'),
                'count' => $rows->count(),
                'items' => $rows,
            ];
        })->values();

        $yearTotal = (float) $expenditures->sum('amount');

        return view('expenditures.index', compact(
            'expenditures',
            'years',
            'selectedYear',
            'fy',
            'monthSummaries',
            'yearTotal'
        ));
    }

    public function show(Expenditure $expenditure)
    {
        $monthlyRows = Expenditure::where('financial_year_id', $expenditure->financial_year_id)
            ->where('name', $expenditure->name)
            ->orderByRaw('month IS NULL, month')
            ->get();

        $monthlyBreakdown = $monthlyRows->map(function (Expenditure $row): array {
            return [
                'month' => $row->month,
                'month_name' => $row->month_name,
                'amount' => (float) $row->amount,
            ];
        });

        $total = (float) $monthlyRows->sum('amount');
        return view('expenditures.show', compact('expenditure', 'monthlyRows', 'monthlyBreakdown', 'total'));
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
            'name' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'month' => 'nullable|integer|min:1|max:12',
        ]);

        $data['name'] = trim((string) preg_replace('/\s+/', ' ', $data['name']));
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
            'name' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'month' => 'nullable|integer|min:1|max:12',
        ]);

        $data['name'] = trim((string) preg_replace('/\s+/', ' ', $data['name']));
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

    /**
     * Spreadsheet compatibility helper:
     * converts matrix-like rows (name + month columns) into normalized records.
     */
    private function normalizeSheetRows(array $rows, int $financialYearId): array
    {
        $records = [];
        foreach ($rows as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            foreach (\App\Models\Payment::MONTHS as $month => $monthName) {
                $value = (float) ($row[strtolower($monthName)] ?? $row[$monthName] ?? 0);
                if ($value <= 0) {
                    continue;
                }
                $records[] = [
                    'financial_year_id' => $financialYearId,
                    'name' => $name,
                    'month' => $month,
                    'amount' => $value,
                ];
            }
        }
        return $records;
    }
}
