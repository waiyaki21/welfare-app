<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankBalance extends Model
{
    protected $fillable = ['financial_year_id', 'month', 'opening_balance', 'closing_balance', 'notes'];

    protected $casts = [
        'opening_balance' => 'float',
        'closing_balance' => 'float',
        'month'           => 'integer',
    ];

    public function financialYear()
    {
        return $this->belongsTo(FinancialYear::class);
    }

    public function getMonthNameAttribute(): string
    {
        return Payment::MONTHS[$this->month] ?? "Month {$this->month}";
    }
}
