<?php

namespace App\Services;

use App\Models\Member;
use App\Models\FinancialYear;
use App\Models\Payment;
use App\Models\WelfareEvent;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class MonthlyImportService
{
    // ── Template export ───────────────────────────────────────────────────────

	/**
	 * Generate a template xlsx for a given year/month with all active members listed.
	 * Returns the path to the temp file.
	 */
	public function exportTemplate(int $year, int $month): string
	{
		$fy      = FinancialYear::where('year', $year)->first();
		$members = Member::orderBy('name')->get();

		$spreadsheet = new Spreadsheet();
		$sheet       = $spreadsheet->getActiveSheet();
		$monthName   = Payment::MONTHS[$month] ?? "Month $month";
		$sheet->setTitle("Monthly Import");

		// ── Header info rows ─────────────────────────────────────────────────
		$sheet->setCellValue('A1', "Athoni Welfare — Monthly Payments Import");
		$sheet->mergeCells('A1:D1');
		$sheet->getStyle('A1')->applyFromArray([
			'font'      => ['bold' => true, 'size' => 13, 'color' => ['argb' => 'FF1A3A2A']],
			'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
		]);

		$sheet->setCellValue('A2', "Year: {$year}   |   Month: {$monthName}");
		$sheet->mergeCells('A2:D2');
		$sheet->getStyle('A2')->applyFromArray([
			'font'      => ['italic' => true, 'size' => 10, 'color' => ['argb' => 'FF52b788']],
			'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
		]);

		$sheet->setCellValue('A3', "Instructions: Fill in Payment and/or Welfare amounts. Leave blank to skip. Do NOT change member names or the year/month rows.");
		$sheet->mergeCells('A3:D3');
		$sheet->getStyle('A3')->applyFromArray([
			'font'  => ['italic' => true, 'size' => 9, 'color' => ['argb' => 'FF6b7280']],
		]);

		// Hidden meta row for the importer to read year/month reliably
		$sheet->setCellValue('A4', '__META__');
		$sheet->setCellValue('B4', $year);
		$sheet->setCellValue('C4', $month);
		$sheet->getRowDimension(4)->setVisible(false);

		// ── Column headers ───────────────────────────────────────────────────
		$headers = ['A5' => 'Member Name', 'B5' => 'Payment (KES)', 'C5' => 'Welfare (KES)', 'D5' => 'Notes'];
		foreach ($headers as $cell => $label) {
			$sheet->setCellValue($cell, $label);
		}
		$sheet->getStyle('A5:D5')->applyFromArray([
			'font'      => ['bold' => true, 'color' => ['argb' => 'FFD8F3DC'], 'size' => 10],
			'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1A3A2A']],
			'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
			'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
		]);

		// ── Member rows ──────────────────────────────────────────────────────
		$row = 6;
		foreach ($members as $member) {
			// Pre-fill existing payment for this month if it exists
			$existingPayment = null;
			$existingWelfare = null;

			if ($fy) {
				$existingPayment = Payment::where('member_id', $member->id)
					->where('financial_year_id', $fy->id)
					->where('month', $month)
					->sum('amount');

				$existingWelfare = WelfareEvent::where('member_id', $member->id)
					->where('financial_year_id', $fy->id)
					->whereMonth('event_date', $month)
					->sum('amount');
			}

			$sheet->setCellValue("A{$row}", $member->name);
			if ($existingPayment > 0) {
				$sheet->setCellValue("B{$row}", $existingPayment);
				// Light tint to show it's pre-filled
				$sheet->getStyle("B{$row}")->getFill()
					->setFillType(Fill::FILL_SOLID)
					->getStartColor()->setARGB('FFD8F3DC');
			}
			if ($existingWelfare > 0) {
				$sheet->setCellValue("C{$row}", $existingWelfare);
				$sheet->getStyle("C{$row}")->getFill()
					->setFillType(Fill::FILL_SOLID)
					->getStartColor()->setARGB('FFFEF3C7');
			}

			// Alternate row tint
			if ($row % 2 === 0) {
				$sheet->getStyle("A{$row}:D{$row}")->getFill()
					->setFillType(Fill::FILL_SOLID)
					->getStartColor()->setARGB('FFF9FAFB');
			}

			$sheet->getStyle("A{$row}:D{$row}")->getBorders()
				->getAllBorders()->setBorderStyle(Border::BORDER_THIN)
				->getColor()->setARGB('FFE5E7EB');

			$row++;
		}

		// ── Column widths ────────────────────────────────────────────────────
		$sheet->getColumnDimension('A')->setWidth(32);
		$sheet->getColumnDimension('B')->setWidth(16);
		$sheet->getColumnDimension('C')->setWidth(16);
		$sheet->getColumnDimension('D')->setWidth(28);

		$sheet->freezePane('A6');

		// ── Save ─────────────────────────────────────────────────────────────
		$path = storage_path("app/exports/monthly_template_{$year}_{$month}.xlsx");
		@mkdir(dirname($path), 0755, true);

		(new Xlsx($spreadsheet))->save($path);
		return $path;
	}

    // ── Monthly import ────────────────────────────────────────────────────────

	/**
	 * Import a filled-in monthly template.
	 *
	 * Rules (as specified):
	 *  - If a member already has a payment for that month → skip (keep existing)
	 *  - If new payment value provided and no existing → create it
	 *  - Same for welfare events
	 *
	 * Returns results array.
	 */
	public function import(string $filePath, int $year, int $month): array
	{
		$results = [
			'year'              => $year,
			'month'             => Payment::MONTHS[$month] ?? $month,
			'payments_created'  => 0,
			'payments_skipped'  => 0,
			'welfare_created'   => 0,
			'welfare_skipped'   => 0,
			'members_not_found' => [],
			'errors'            => [],
		];

		$fy = FinancialYear::where('year', $year)->first();
		if (!$fy) {
			$results['errors'][] = "Financial year {$year} not found. Please create it first by importing the full year spreadsheet.";
			return $results;
		}

		$reader      = new XlsxReader();
		$spreadsheet = $reader->load($filePath);
		$sheet       = $spreadsheet->getActiveSheet();
		$highestRow  = $sheet->getHighestRow();

		// Detect data start row — look for the __META__ marker or the header row
		$dataStartRow = 6; // default based on template structure
		for ($r = 1; $r <= min(10, $highestRow); $r++) {
			if (trim((string) $sheet->getCell("A{$r}")->getValue()) === '__META__') {
				// Read year/month from hidden meta row if present (ignore passed values if template has them)
				$dataStartRow = $r + 2; // skip meta + header
				break;
			}
		}

		// Build member lookup: normalized name → Member
		$members = Member::all()->keyBy(fn($m) => $this->normalizeName($m->name));

		for ($r = $dataStartRow; $r <= $highestRow; $r++) {
			$rawName = trim((string) $sheet->getCell("A{$r}")->getValue());
			if (empty($rawName)) continue;

			$paymentAmt = $this->toFloat($sheet->getCell("B{$r}")->getValue());
			$welfareAmt = $this->toFloat($sheet->getCell("C{$r}")->getValue());
			$notes      = trim((string) $sheet->getCell("D{$r}")->getValue());

			// Skip completely empty data rows
			if ($paymentAmt <= 0 && $welfareAmt <= 0) continue;

			// Find member
			$member = $members->get($this->normalizeName($rawName));
			if (!$member) {
				$results['members_not_found'][] = $rawName;
				$results['errors'][] = "Row {$r}: Member \"{$rawName}\" not found — skipped.";
				continue;
			}

			// ── Payment ──────────────────────────────────────────────────────
			if ($paymentAmt > 0) {
				$existingPayment = Payment::where('member_id', $member->id)
					->where('financial_year_id', $fy->id)
					->where('month', $month)
					->exists();

				if ($existingPayment) {
					$results['payments_skipped']++;
				} else {
					Payment::create([
						'member_id'         => $member->id,
						'financial_year_id' => $fy->id,
						'month'             => $month,
						'amount'            => $paymentAmt,
						'payment_type'      => 'contribution',
						'notes'             => $notes ?: null,
					]);
					$results['payments_created']++;
				}
			}

			// ── Welfare event ─────────────────────────────────────────────────
			if ($welfareAmt > 0) {
				// Check if a welfare event already exists for this member in this month
				$existingWelfare = WelfareEvent::where('member_id', $member->id)
					->where('financial_year_id', $fy->id)
					->whereYear('event_date', $year)
					->whereMonth('event_date', $month)
					->exists();

				if ($existingWelfare) {
					$results['welfare_skipped']++;
				} else {
					WelfareEvent::create([
						'member_id'         => $member->id,
						'financial_year_id' => $fy->id,
						'amount'            => $welfareAmt,
						'reason'            => 'general',
						'event_date'        => \Carbon\Carbon::create($year, $month, 1)->endOfMonth()->toDateString(),
						'notes'             => $notes ?: null,
					]);
					$results['welfare_created']++;
				}
			}
		}

		return $results;
	}

	// ── Helpers ───────────────────────────────────────────────────────────────

	private function normalizeName(string $name): string
	{
		return strtolower(trim(preg_replace('/\s+/', ' ', $name)));
	}

	private function toFloat(mixed $val): float
	{
		if ($val === null || $val === '') return 0.0;
		$str = strtolower(trim((string) $val));
		if (in_array($str, ['nan', 'none', '-', '—'])) return 0.0;
		return (float) str_replace([',', ' '], '', $str);
	}
}
