<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\FinancialYear;
use App\Models\Member;
use App\Models\Payment;
use App\Services\ExpenditureImportService;
use App\Services\MonthlyImportService;
use App\Services\SpreadsheetImportService;
use Illuminate\Http\Request;
use Native\Laravel\Facades\Notification as NativeNotification;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ImportController extends Controller
{
    public function __construct(
        private SpreadsheetImportService $importer,
        private MonthlyImportService     $monthlyImporter,
        private ExpenditureImportService $expenditureImporter
    ) {}

    // ── Full-year import ──────────────────────────────────────────────────────

    public function show()
    {
        try {
            $years        = FinancialYear::orderByDesc('year')->pluck('year');
            $selectedYear = (int) request()->get('year', $years->first() ?? date('Y'));
            $minYearRaw   = Member::whereNotNull('joined_year')->min('joined_year');
            $minYear      = $minYearRaw ? (int) $minYearRaw : (int) date('Y');
            $maxYear      = (int) date('Y') + 1;
            if ($minYear > $maxYear) {
                $minYear = $maxYear;
            }
            $financialYears = FinancialYear::orderBy('year')->pluck('year');
            $hasFinancialYears = FinancialYear::exists();

            $importStates = [
                'year' => AppSetting::importState('year'),
                'month' => AppSetting::importState('month'),
                'expenditure' => AppSetting::importState('expenditure'),
            ];

            $this->notifyImportLoad(true);

            return view('imports.index', compact(
                'years',
                'selectedYear',
                'minYear',
                'maxYear',
                'financialYears',
                'hasFinancialYears',
                'importStates'
            ));
        } catch (\Throwable $e) {
            $this->notifyImportLoad(false);
            throw $e;
        }
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

    public function expenditureTemplate(int $year)
    {
        $year = (int) $year;
        if (!FinancialYear::where('year', $year)->exists()) {
            abort(404);
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle("Expenditures {$year}");

        $headers = ['Narration / Expense', 'Amount (KES)'];
        foreach ($headers as $index => $label) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index + 1);
            $sheet->setCellValue("{$col}1", $label);
        }

        $sheet->getStyle('A1:B1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFD8F3DC'], 'size' => 10],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1A3A2A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        $row = 2;
        $sampleNarrations = ['General', 'Operations', 'Travel & Meetings'];
        $sampleExpenses = [
            'General' => ['Bank Charges', 'Office Supplies'],
            'Operations' => ['Secretary Allowance', 'Audit Fees'],
            'Travel & Meetings' => ['Transport', 'Venue & Meals'],
        ];

        foreach ($sampleNarrations as $narration) {
            $sheet->setCellValue("A{$row}", $narration);
            $sheet->getStyle("A{$row}:B{$row}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['argb' => 'FF14532D']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFD8F3DC']],
            ]);
            $row++;

            foreach ($sampleExpenses[$narration] ?? [] as $expenseName) {
                $sheet->setCellValue("A{$row}", $expenseName);
                $sheet->setCellValue("B{$row}", 0);
                $row++;
            }

            $row++;
        }

        $sheet->getColumnDimension('A')->setWidth(40);
        $sheet->getColumnDimension('B')->setWidth(16);

        $path = storage_path("app/exports/expenditures_template_{$year}.xlsx");
        @mkdir(dirname($path), 0755, true);
        (new Xlsx($spreadsheet))->save($path);

        $filename = "Expenditures_Template_{$year}.xlsx";
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
            return $this->previewErrorResponse($e, 'Spreadsheet could not be processed');
        } finally {
            @unlink($fullPath);
        }
    }

    public function previewLastUpload(Request $request)
    {
        $type = $request->input('type');

        if (!$type) {
            return response()->json(['message' => 'Type is required'], 422);
        }

        $upload = AppSetting::getLastUpload($type);

        if (!$upload || !isset($upload['storage_path'])) {
            return response()->json(['message' => 'No last upload found'], 404);
        }

        $fullPath = storage_path('app/' . $upload['storage_path']);

        if (!file_exists($fullPath)) {
            return response()->json(['message' => 'File not found'], 404);
        }

        try {
            $preview = $this->importer->preview($fullPath);
            return response()->json($preview);
        } catch (\Throwable $e) {
            return $this->previewErrorResponse($e, 'Spreadsheet could not be processed');
        }
        // ✅ No finally @unlink — the saved file must not be deleted on preview
    }

    public function finalYearImport(Request $request)
    {
        $useLastUpload = filter_var($request->input('use_last_upload'), FILTER_VALIDATE_BOOLEAN);
        $memberOverrides = $this->decodeMemberOverrides($request->input('member_overrides'));

        if ($useLastUpload) {
            // ── Use the previously saved file from AppSetting ────────────────
            $upload = AppSetting::getLastUpload('year');

            if (!$upload || !isset($upload['storage_path'])) {
                return $this->importErrorResponse($request, 'No last upload found.');
            }

            $fullPath = storage_path('app/' . $upload['storage_path']);

            if (!file_exists($fullPath)) {
                return $this->importErrorResponse($request, 'Last uploaded file no longer exists.');
            }

            $originalName = $upload['file_name'];
        } else {
            // ── Regular file upload path ─────────────────────────────────────
            $request->validate([
                'spreadsheet'      => 'required|file|mimes:xlsx,xls|max:20480',
                'removed_members'  => 'nullable|string',
                'removed_payments' => 'nullable|string',
                'removed_expenses' => 'nullable|string',
            ]);

            $file         = $request->file('spreadsheet');
            $originalName = $file->getClientOriginalName();
            $path         = $file->storeAs('imports', $originalName);
            $fullPath     = storage_path("app/{$path}");
        }

        $saved = false;

        try {
            $feedback = $this->importer->import($fullPath, [
                'removed_members'  => $this->decodeJsonArray($request->input('removed_members')),
                'removed_payments' => $this->decodeJsonArray($request->input('removed_payments')),
                'removed_expenses' => $this->decodeJsonArray($request->input('removed_expenses')),
                'member_overrides' => $memberOverrides,
            ]);

            // Save/refresh last upload record only after a successful import
            $storagePath = 'imports/' . $originalName;
            $this->saveLastUpload('year', $originalName, $storagePath);
            $saved = true;

            if ($request->expectsJson()) {
                return response()->json($feedback);
            }

            return redirect()->route('dashboard')
                ->with('import_feedback', $feedback)
                ->with('import_results', $feedback)
                ->with('success', 'Import completed successfully.');
        } catch (\Throwable $e) {
            return $this->importErrorResponse($request, 'Final import failed: ' . $e->getMessage());
        } finally {
            // Only delete if this was a fresh upload AND it failed (keep saved files)
            if (!$saved && !$useLastUpload) {
                @unlink($fullPath);
            }
        }
    }

    // Add this small private helper to avoid duplicating error responses:
    private function importErrorResponse(Request $request, string $message)
    {
        if ($request->expectsJson()) {
            return response()->json(['status' => 'error', 'message' => $message], 422);
        }
        return redirect()->route('dashboard')->withErrors(['spreadsheet' => $message]);
    }

    private function previewErrorResponse(\Throwable $e, string $fallbackMessage)
    {
        $payload = json_decode($e->getMessage(), true);

        if (is_array($payload) && ($payload['status'] ?? null) === 'error') {
            return response()->json([
                'status' => 'error',
                'message' => $payload['message'] ?? $fallbackMessage,
                'errors' => $payload['errors'] ?? [],
            ], 422);
        }

        return response()->json([
            'status' => 'error',
            'message' => $fallbackMessage,
            'errors' => [],
        ], 422);
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

    public function previewExpenditureImport(Request $request)
    {
        $request->validate([
            'spreadsheet' => 'required|file|mimes:xlsx,xls|max:20480',
            'year' => 'required|integer|min:2000|max:2100',
        ]);

        $year = (int) $request->input('year');
        $path = $request->file('spreadsheet')->store('imports');
        $fullPath = storage_path("app/{$path}");

        try {
            return response()->json($this->expenditureImporter->preview($fullPath, $year));
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'The expenditure spreadsheet could not be read',
                'details' => $e->getMessage(),
            ], 422);
        } finally {
            @unlink($fullPath);
        }
    }

    public function finalExpenditureImport(Request $request)
    {
        $request->validate([
            'spreadsheet' => 'required|file|mimes:xlsx,xls|max:20480',
            'year' => 'required|integer|min:2000|max:2100',
            'removed_expenditures' => 'nullable|string',
        ]);

        $year = (int) $request->input('year');
        $path = $request->file('spreadsheet')->store('imports');
        $fullPath = storage_path("app/{$path}");

        try {
            $feedback = $this->expenditureImporter->import($fullPath, $year, [
                'removed_expenditures' => $this->decodeJsonArray($request->input('removed_expenditures')),
            ]);

            if ($request->expectsJson()) {
                return response()->json($feedback);
            }

            return redirect()->route('dashboard', ['year' => $year])
                ->with('import_feedback', $feedback)
                ->with('success', 'Expenditure import completed successfully.');
        } catch (\Throwable $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Expenditure import failed: ' . $e->getMessage(),
                ], 422);
            }

            return redirect()->route('dashboard', ['year' => $year])
                ->withErrors(['spreadsheet' => 'Expenditure import failed: ' . $e->getMessage()]);
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

    private function decodeMemberOverrides(?string $value): array
    {
        if (!$value) {
            return [];
        }

        $decoded = json_decode($value, true);
        if (!is_array($decoded)) {
            return [];
        }

        $overrides = [];
        foreach ($decoded as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $row = (int) ($entry['row'] ?? 0);
            $name = trim((string) ($entry['name'] ?? ''));
            $phone = trim((string) ($entry['phone'] ?? ''));

            if ($row <= 0) {
                continue;
            }

            $overrides[] = [
                'row' => $row,
                'name' => $name,
                'phone' => $phone,
            ];
        }

        return $overrides;
    }

    protected function saveLastUpload(string $type, string $originalName, string $storagePath): void
    {
        AppSetting::set("last_{$type}_upload", json_encode([
            'file_name'    => $originalName,
            'storage_path' => $storagePath,   // relative path e.g. "imports/MySheet.xlsx"
            'uploaded_at'  => now()->toDateTimeString(),
        ]));
    }

    private function notifyImportLoad(bool $success): void
    {
        if (!class_exists(NativeNotification::class)) {
            return;
        }

        try {
            if ($success) {
                NativeNotification::title('Load Successful')
                    ->message('Import page load successfully.')
                    ->send();
            } else {
                NativeNotification::title('Load Failed')
                    ->message('Import Page not loaded.')
                    ->send();
            }
        } catch (\Throwable $e) {
            // Ignore notification failures to keep web flow intact.
        }
    }
}
