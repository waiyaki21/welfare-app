<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberFinancial extends Model
{
    protected $fillable = [
        'member_id',
        'financial_year_id',
        'contributions_brought_forward',
        'contributions_carried_forward',
        'total_welfare',
        'development',
        'welfare_owing',
        'total_investment',
        'pct_share',
        'notes',
    ];

    protected $casts = [
        'contributions_brought_forward' => 'float',
        'contributions_carried_forward' => 'float',
        'total_welfare'                 => 'float',
        'development'                   => 'float',
        'welfare_owing'                 => 'float',
        'total_investment'              => 'float',
        'pct_share'                     => 'float',
    ];

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function financialYear()
    {
        return $this->belongsTo(FinancialYear::class);
    }

    public function getStatusAttribute(): string
    {
        if ($this->welfare_owing > 0)   return 'surplus';
        if ($this->welfare_owing < 0)   return 'deficit';
        return 'break_even';
    }

    public function getPctShareFormattedAttribute(): string
    {
        return number_format($this->pct_share * 100, 2) . '%';
    }
}
