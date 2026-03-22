<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'member_id',
        'financial_year_id',
        'month',
        'amount',
        'payment_type',
        'notes',
    ];

    protected $casts = [
        'amount' => 'float',
        'month'  => 'integer',
    ];

    const TYPES = [
        'contribution' => 'Contribution',
        'arrears'      => 'Arrears',
        'lump_sum'     => 'Lump Sum',
    ];

    const MONTHS = [
        1  => 'January',   2  => 'February', 3  => 'March',
        4  => 'April',     5  => 'May',       6  => 'June',
        7  => 'July',      8  => 'August',    9  => 'September',
        10 => 'October',   11 => 'November',  12 => 'December',
    ];

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function financialYear()
    {
        return $this->belongsTo(FinancialYear::class);
    }

    public function getMonthNameAttribute(): string
    {
        return self::MONTHS[$this->month] ?? "Month {$this->month}";
    }

    public function getTypeNameAttribute(): string
    {
        return self::TYPES[$this->payment_type] ?? ucfirst($this->payment_type);
    }

    public function scopeForYear($query, int $year)
    {
        return $query->whereHas('financialYear', fn ($q) => $q->where('year', $year));
    }

    public function scopeForMonth($query, int $month)
    {
        return $query->where('month', $month);
    }
}
