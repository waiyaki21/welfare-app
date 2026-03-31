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
                        ->when($filter === 'active',   fn ($q) => $q->where('is_active', true))
            ->when($filter === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when($memberIds,             fn ($q) => $q->whereIn('id', $memberIds))
            ->orderBy('name')
            ->get();

        $stats = $fy ? [
            'total'   => $fy->memberFinancials()->count(),
            'active'  => Member::active()->count(),
            'deficit' => $fy->membersInDeficit(),
            'surplus' => $fy->memberFinancials()->where('welfare_owing', '>=', 0)->count(),
        ] : [];

        $memberRows = $members->map(function ($member) {
            $fin = $member->financials->first();
            $statusValue = $fin
                ? ($fin->welfare_owing >= 0 ? 'surplus' : 'deficit')
                : 'n/a';

            $statusLabel = $fin
                ? ($fin->welfare_owing >= 0
                    ? '<span class="badge badge-g">Surplus</span>'
                    : '<span class="badge badge-r">Deficit ' . number_format(abs($fin->welfare_owing)) . '</span>')
                : '—';

            if (!$member->is_active) {
                $statusLabel = '<span class="badge badge-mid" style="font-size:.65rem">Inactive</span>';
                $statusValue = 'inactive';
            }

            $memberCell = '<div class="flex items-center gap-3">'
                . '<div class="avatar avatar-sm" style="' . (!$member->is_active ? 'opacity:.5' : '') . '">' . e($member->initials) . '</div>'
                . '<div>'
                . '<a href="' . route('members.show', $member->id) . '" style="font-weight:500;color:var(--forest);text-decoration:none;">' . e($member->name) . '</a>'
                . '</div>'
                . '</div>';

            $actions = '<div class="flex items-center gap-1">'
                . '<a href="' . route('members.edit', $member) . '" class="btn btn-ghost btn-xs">Edit</a>'
                . '<form method="POST" action="' . route('members.destroy', $member) . '" onsubmit="return confirm(\'Delete this member permanently?\')">'
                . csrf_field()
                . method_field('DELETE')
                . '<button type="submit" class="btn btn-ghost btn-xs" style="color:var(--rust)">Del</button>'
                . '</form>'
                . '</div>';

            return [
                'member' => $memberCell,
                'phone' => $member->phone ?? '—',
                'joined' => $member->joined_year ?? '—',
                'contrib_cf' => $fin ? number_format($fin->contributions_carried_forward) : '—',
                'welfare' => $fin ? number_format($fin->total_welfare) : '—',
                'investment' => $fin ? number_format($fin->total_investment) : '—',
                'status' => $statusLabel,
                'actions' => $actions,
                '__values' => [
                    'member' => $member->name,
                    'phone' => $member->phone ?? '',
                    'joined' => $member->joined_year ?? 0,
                    'contrib_cf' => $fin?->contributions_carried_forward ?? 0,
                    'welfare' => $fin?->total_welfare ?? 0,
                    'investment' => $fin?->total_investment ?? 0,
                    'status' => $statusValue,
                    'actions' => '',
                ],
            ];
        });

        return view('members.index', compact('members', 'memberRows', 'years', 'selectedYear', 'search', 'filter', 'stats', 'fy'));
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

