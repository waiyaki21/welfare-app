<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Expenditure extends Model
{
    use HasFactory;

    protected $fillable = [
        'financial_year_id',
        'name',
        'amount',
        'month',
    ];

    public function financialYear()
    {
        return $this->belongsTo(FinancialYear::class);
    }

    public function getMonthNameAttribute(): string
    {
        if (!$this->month) {
            return '-';
        }
        return Payment::MONTHS[$this->month] ?? (string) $this->month;
    }
}

