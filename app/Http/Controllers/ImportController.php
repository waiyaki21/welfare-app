<?php

namespace App\Http\Controllers;

use App\Services\SpreadsheetImportService;
use Illuminate\Http\Request;

class ImportController extends Controller
{
    public function __construct(private SpreadsheetImportService $importer) {}

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
}
