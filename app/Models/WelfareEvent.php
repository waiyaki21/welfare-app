<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WelfareEvent extends Model
{
    protected $fillable = [
        'member_id',
        'financial_year_id',
        'amount',
        'reason',
        'event_date',
        'notes',
    ];

    protected $casts = [
        'amount'     => 'float',
        'event_date' => 'date',
    ];

    const REASONS = [
        'bereavement' => 'Bereavement',
        'illness'     => 'Illness',
        'emergency'   => 'Emergency',
        'general'     => 'General',
    ];

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function financialYear()
    {
        return $this->belongsTo(FinancialYear::class);
    }

    public function getReasonNameAttribute(): string
    {
        return self::REASONS[$this->reason] ?? ucfirst($this->reason);
    }
}
