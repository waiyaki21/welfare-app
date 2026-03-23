<?php

namespace App\Http\Controllers;

// use App\Models\FinancialYear;
use App\Models\Payment;
use App\Services\SpreadsheetImportService;
use App\Services\MonthlyImportService;
use Illuminate\Http\Request;

class ImportController extends Controller
{
    public function __construct(
        private SpreadsheetImportService $importer,
        private MonthlyImportService     $monthlyImporter
    ) {}

    // ── Full-year import ──────────────────────────────────────────────────────

    public function show()
    {
        return view('import.index');
    }

    public function store(Request $request)
    {
        $request->validate([
            'spreadsheet' => 'required|file|mimes:xlsx,xls|max:20480',
        ]);

        $path     = $request->file('spreadsheet')->store('imports');
        $fullPath = storage_path("app/{$path}");

        try {
            $results = $this->importer->import($fullPath);
            return redirect()->route('dashboard')
                ->with('import_results', $results)
                ->with('success', 'Import completed successfully.');
        } catch (\Throwable $e) {
            return redirect()->route('dashboard')
                ->withErrors(['spreadsheet' => 'Import failed: ' . $e->getMessage()]);
        } finally {
            @unlink($fullPath);
        }
    }

    // ── Monthly template download ─────────────────────────────────────────────

    public function monthlyTemplate(Request $request)
    {
        $request->validate([
            'year'  => 'required|integer|min:2000|max:2100',
            'month' => 'required|integer|min:1|max:12',
        ]);

        $year  = (int) $request->year;
        $month = (int) $request->month;

        $path     = $this->monthlyImporter->exportTemplate($year, $month);
        $monthName = Payment::MONTHS[$month] ?? "Month{$month}";
        $filename  = "Athoni_Monthly_{$year}_{$monthName}.xlsx";

        return response()->download($path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    // ── Monthly import ────────────────────────────────────────────────────────

    public function storeMonthly(Request $request)
    {
        $request->validate([
            'spreadsheet' => 'required|file|mimes:xlsx,xls|max:20480',
            'year'        => 'required|integer|min:2000|max:2100',
            'month'       => 'required|integer|min:1|max:12',
        ]);

        $year     = (int) $request->year;
        $month    = (int) $request->month;
        $path     = $request->file('spreadsheet')->store('imports');
        $fullPath = storage_path("app/{$path}");

        try {
            $results = $this->monthlyImporter->import($fullPath, $year, $month);
            return redirect()->route('dashboard')
                ->with('monthly_import_results', $results)
                ->with('success', "Monthly import for {$results['month']} {$year} completed.");
        } catch (\Throwable $e) {
            return redirect()->route('dashboard')
                ->withErrors(['spreadsheet' => 'Monthly import failed: ' . $e->getMessage()]);
        } finally {
            @unlink($fullPath);
        }
    }
}
