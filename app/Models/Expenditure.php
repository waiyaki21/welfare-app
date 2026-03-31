<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Expenditure extends Model
{
    use HasFactory;

    protected $fillable = [
        'financial_year_id',
        'narration',
        'name',
        'amount',
    ];

    public function financialYear()
    {
        return $this->belongsTo(FinancialYear::class);
    }

    public function getFormattedAmountAttribute(): string
    {
        return number_format((float) $this->amount, 2);
    }

    public function scopeGroupedByNarration($query)
    {
        return $query->orderByRaw('narration IS NULL, narration')
            ->orderBy('name');
    }
}
