<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinancialYear extends Model
{
    protected $fillable = ['year', 'sheet_name', 'welfare_per_member', 'is_current'];

    protected $casts = ['is_current' => 'boolean'];

    // ── Relationships ────────────────────────────────────────────────────────

    public function memberFinancials()
    {
        return $this->hasMany(MemberFinancial::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }

    public function welfareEvents()
    {
        return $this->hasMany(WelfareEvent::class);
    }

    public function expenditures()
    {
        return $this->hasMany(Expenditure::class);
    }

    public function bankBalances()
    {
        return $this->hasMany(BankBalance::class)->orderBy('month');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    public static function current(): ?self
    {
        return static::where('is_current', true)->first()
            ?? static::orderByDesc('year')->first();
    }

    public function totalContributions(): float
    {
        return (float) $this->payments()->sum('amount');
    }

    public function totalExpenses(): float
    {
        return (float) $this->expenses()->sum('amount');
    }

    public function totalWelfare(): float
    {
        return (float) $this->memberFinancials()->sum('total_welfare');
    }

    public function totalInvestment(): float
    {
        return (float) $this->memberFinancials()->sum('total_investment');
    }

    public function membersInDeficit(): int
    {
        return $this->memberFinancials()->where('welfare_owing', '<', 0)->count();
    }

    public function monthlyTotals(): array
    {
        $rows = $this->payments()
            ->selectRaw('month, SUM(amount) as total')
            ->groupBy('month')
            ->pluck('total', 'month')
            ->toArray();

        $out = [];
        for ($m = 1; $m <= 12; $m++) {
            $out[$m] = (float) ($rows[$m] ?? 0);
        }
        return $out;
    }

    // ── Latest Monthly Payments ───────────────────────────────────────────────

    /**
     * Get latest monthly summary: month name, count, total amount
     */
    public function latestMonthlySummary(): array
    {
        $latestPayment = $this->payments()
            ->orderByDesc('month')
            ->first();

        if (!$latestPayment) {
            return [
                'month_number' => null,
                'month_name'   => null,
                'count'        => 0,
                'total'        => 0,
            ];
        }

        $latestMonth = $latestPayment->month;

        $payments = $this->payments()->where('month', $latestMonth);

        return [
            'month_number' => $latestMonth,
            'month_name'   => date('F', mktime(0, 0, 0, $latestMonth, 1)), // e.g., "March"
            'count'        => $payments->count(),
            'total'        => (float) $payments->sum('amount'),
        ];
    }
}
