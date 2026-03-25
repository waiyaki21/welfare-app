<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\MonthlyImportService;
use App\Services\SpreadsheetImportService;
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
        return $this->finalYearImport($request);
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
        return $this->finalMonthImport($request);
    }

    public function previewYearImport(Request $request)
    {
        $request->validate([
            'spreadsheet' => 'required|file|mimes:xlsx,xls|max:20480',
        ]);

        $path = $request->file('spreadsheet')->store('imports');
        $fullPath = storage_path("app/{$path}");

        try {
            $preview = $this->importer->preview($fullPath);
            return response()->json($preview);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'The spreadsheet could not be read',
                'details' => $e->getMessage(),
            ], 422);
        } finally {
            @unlink($fullPath);
        }
    }

    public function finalYearImport(Request $request)
    {
        $request->validate([
            'spreadsheet' => 'required|file|mimes:xlsx,xls|max:20480',
            'removed_members' => 'nullable|string',
            'removed_payments' => 'nullable|string',
            'removed_expenses' => 'nullable|string',
        ]);

        $path = $request->file('spreadsheet')->store('imports');
        $fullPath = storage_path("app/{$path}");

        try {
            $feedback = $this->importer->import($fullPath, [
                'removed_members' => $this->decodeJsonArray($request->input('removed_members')),
                'removed_payments' => $this->decodeJsonArray($request->input('removed_payments')),
                'removed_expenses' => $this->decodeJsonArray($request->input('removed_expenses')),
            ]);

            if ($request->expectsJson()) {
                return response()->json($feedback);
            }

            return redirect()->route('dashboard')
                ->with('import_feedback', $feedback)
                ->with('import_results', $feedback)
                ->with('success', 'Import completed successfully.');
        } catch (\Throwable $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Final import failed: ' . $e->getMessage(),
                ], 422);
            }

            return redirect()->route('dashboard')
                ->withErrors(['spreadsheet' => 'Import failed: ' . $e->getMessage()]);
        } finally {
            @unlink($fullPath);
        }
    }

    public function previewMonthImport(Request $request)
    {
        $request->validate([
            'spreadsheet' => 'required|file|mimes:xlsx,xls|max:20480',
            'year'        => 'required|integer|min:2000|max:2100',
            'month'       => 'required|integer|min:1|max:12',
        ]);

        $year = (int) $request->year;
        $month = (int) $request->month;
        $path = $request->file('spreadsheet')->store('imports');
        $fullPath = storage_path("app/{$path}");

        try {
            $preview = $this->monthlyImporter->preview($fullPath, $year, $month);
            return response()->json($preview);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Monthly preview failed: ' . $e->getMessage(),
            ], 422);
        } finally {
            @unlink($fullPath);
        }
    }

    public function finalMonthImport(Request $request)
    {
        $request->validate([
            'spreadsheet' => 'required|file|mimes:xlsx,xls|max:20480',
            'year'        => 'required|integer|min:2000|max:2100',
            'month'       => 'required|integer|min:1|max:12',
        ]);

        $year = (int) $request->year;
        $month = (int) $request->month;
        $path = $request->file('spreadsheet')->store('imports');
        $fullPath = storage_path("app/{$path}");

        try {
            $results = $this->monthlyImporter->import($fullPath, $year, $month);

            if ($request->expectsJson()) {
                return response()->json($results);
            }

            return redirect()->route('dashboard')
                ->with('monthly_import_results', $results)
                ->with('success', "Monthly import for {$results['month']} {$year} completed.");
        } catch (\Throwable $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Monthly import failed: ' . $e->getMessage(),
                ], 422);
            }

            return redirect()->route('dashboard')
                ->withErrors(['spreadsheet' => 'Monthly import failed: ' . $e->getMessage()]);
        } finally {
            @unlink($fullPath);
        }
    }

    private function decodeJsonArray(?string $value): array
    {
        if (!$value) {
            return [];
        }

        $decoded = json_decode($value, true);
        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_filter($decoded, fn($v) => is_string($v) && $v !== ''));
    }
}
