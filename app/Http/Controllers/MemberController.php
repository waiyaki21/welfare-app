<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\FinancialYear;
use App\Http\Requests\StoreMemberRequest;
use App\Http\Requests\UpdateMemberRequest;
use Illuminate\Http\Request;

class MemberController extends Controller
{
    public function index(Request $request)
    {
        $years        = FinancialYear::orderByDesc('year')->pluck('year');
        $selectedYear = (int) $request->get('year', $years->first() ?? date('Y'));
        $search       = $request->get('search', '');
        $filter       = $request->get('filter', 'all');

        $fy = FinancialYear::where('year', $selectedYear)->first();

        $memberIds = null;
        if ($fy && in_array($filter, ['deficit', 'surplus'])) {
            $memberIds = $fy->memberFinancials()
                ->when($filter === 'deficit', fn ($q) => $q->where('welfare_owing', '<', 0))
                ->when($filter === 'surplus', fn ($q) => $q->where('welfare_owing', '>=', 0))
                ->pluck('member_id');
        }

        $members = Member::query()
            ->with(['financials' => fn ($q) => $q->where('financial_year_id', optional($fy)->id)->with('financialYear')])
            ->when($search,                fn ($q) => $q->search($search))
            ->when($filter === 'active',   fn ($q) => $q->where('is_active', true))
            ->when($filter === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when($memberIds,             fn ($q) => $q->whereIn('id', $memberIds))
            ->orderBy('name')
            ->paginate(500)   // all rows — client-side table handles paging
            ->withQueryString();

        $stats = $fy ? [
            'total'   => $fy->memberFinancials()->count(),
            'active'  => Member::active()->count(),
            'deficit' => $fy->membersInDeficit(),
            'surplus' => $fy->memberFinancials()->where('welfare_owing', '>=', 0)->count(),
        ] : [];

        return view('members.index', compact('members', 'years', 'selectedYear', 'search', 'filter', 'stats', 'fy'));
    }

    public function create()
    {
        $years = FinancialYear::orderByDesc('year')->pluck('year');
        return view('members.create', compact('years'));
    }

    public function store(StoreMemberRequest $request)
    {
        $member = Member::create($request->validated());
        return redirect()->route('members.show', $member)
            ->with('success', "Member \"{$member->name}\" created.");
    }

    public function show(Member $member)
    {
        $member->load('financials.financialYear');
        $years = FinancialYear::orderByDesc('year')->pluck('year');

        $financials = $member->financials->keyBy(fn ($f) => $f->financialYear->year);

        $paymentsByYear = $member->payments()
            ->with('financialYear')->orderBy('month')->get()
            ->groupBy(fn ($p) => $p->financialYear->year);

        $welfareEvents = $member->welfareEvents()
            ->with('financialYear')->orderByDesc('event_date')->get();

        return view('members.show', compact('member', 'years', 'financials', 'paymentsByYear', 'welfareEvents'));
    }

    public function edit(Member $member)
    {
        return view('members.edit', compact('member'));
    }

    public function update(UpdateMemberRequest $request, Member $member)
    {
        $data              = $request->validated();
        $data['is_active'] = $request->boolean('is_active');
        $member->update($data);

        return redirect()->route('members.show', $member)
            ->with('success', 'Member updated.');
    }

    public function destroy(Member $member)
    {
        $name = $member->name;
        // Hard delete — removes the member and all related records via DB cascade
        $member->payments()->delete();
        $member->welfareEvents()->delete();
        $member->financials()->delete();
        $member->delete();

        return redirect()->route('members.index')
            ->with('success', "\"{$name}\" has been permanently deleted.");
    }
}
