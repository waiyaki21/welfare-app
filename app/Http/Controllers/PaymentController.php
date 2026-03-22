<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Member;
use App\Models\FinancialYear;
use App\Http\Requests\StorePaymentRequest;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $years        = FinancialYear::orderByDesc('year')->pluck('year');
        $selectedYear = (int) $request->get('year', $years->first() ?? date('Y'));
        $search       = $request->get('search', '');
        $monthFilter  = (int) $request->get('month', 0);
        $typeFilter   = $request->get('type', '');

        $fy = FinancialYear::where('year', $selectedYear)->first();

        $payments = Payment::with(['member', 'financialYear'])
            ->when($fy,          fn ($q) => $q->where('financial_year_id', $fy->id))
            ->when($monthFilter, fn ($q) => $q->where('month', $monthFilter))
            ->when($typeFilter,  fn ($q) => $q->where('payment_type', $typeFilter))
            ->when($search, fn ($q) => $q->whereHas('member', fn ($q) => $q->where('name', 'like', "%{$search}%")))
            ->orderBy('month')
            ->orderByDesc('amount')
            ->paginate(30)
            ->withQueryString();

        // Monthly summary for the selected year
        $monthlySummary = $fy ? $fy->monthlyTotals() : [];

        $yearTotal = array_sum($monthlySummary);

        $members = Member::active()->orderBy('name')->get(['id', 'name']);
        $fyAll   = FinancialYear::orderByDesc('year')->get(['id', 'year']);

        return view('payments.index', compact(
            'payments', 'years', 'selectedYear', 'search',
            'monthFilter', 'typeFilter', 'monthlySummary', 'yearTotal',
            'members', 'fyAll', 'fy'
        ));
    }

    public function store(StorePaymentRequest $request)
    {
        $payment = Payment::create($request->validated());
        $payment->load('member');

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'payment' => $payment]);
        }

        return redirect()
            ->route('payments.index', ['year' => $payment->financialYear->year])
            ->with('success', "Payment of KES " . number_format($payment->amount) . " recorded for {$payment->member->name}.");
    }

    public function edit(Payment $payment)
    {
        $members = Member::active()->orderBy('name')->get(['id', 'name']);
        $fyAll   = FinancialYear::orderByDesc('year')->get(['id', 'year']);
        return view('payments.edit', compact('payment', 'members', 'fyAll'));
    }

    public function update(StorePaymentRequest $request, Payment $payment)
    {
        $payment->update($request->validated());
        return redirect()
            ->route('payments.index', ['year' => $payment->financialYear->year])
            ->with('success', 'Payment updated.');
    }

    public function destroy(Payment $payment)
    {
        $year = $payment->financialYear->year;
        $payment->delete();
        return redirect()
            ->route('payments.index', ['year' => $year])
            ->with('success', 'Payment deleted.');
    }

    // Quick-add via member profile page (returns JSON)
    public function quickStore(Request $request)
    {
        $validated = $request->validate([
            'member_id'         => 'required|exists:members,id',
            'financial_year_id' => 'required|exists:financial_years,id',
            'month'             => 'required|integer|min:1|max:12',
            'amount'            => 'required|numeric|min:1',
            'payment_type'      => 'nullable|in:contribution,arrears,lump_sum',
            'notes'             => 'nullable|string|max:500',
        ]);

        $validated['payment_type'] = $validated['payment_type'] ?? 'contribution';
        $payment = Payment::create($validated);
        $payment->load('member', 'financialYear');

        return response()->json([
            'success'    => true,
            'payment'    => $payment,
            'month_name' => $payment->month_name,
            'type_name'  => $payment->type_name,
        ]);
    }
}
