<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    protected $fillable = ['financial_year_id', 'month', 'category', 'amount', 'notes'];

    protected $casts = ['amount' => 'float', 'month' => 'integer'];

    public function financialYear()
    {
        return $this->belongsTo(FinancialYear::class);
    }

    public function expenseCategory()
    {
        return $this->belongsTo(ExpenseCategory::class, 'category', 'slug');
    }

    public function getCategoryNameAttribute(): string
    {
        if ($this->relationLoaded('expenseCategory') && $this->expenseCategory) {
            return $this->expenseCategory->name;
        }
        $cat = ExpenseCategory::where('slug', $this->category)->first();
        return $cat ? $cat->name : ucwords(str_replace('_', ' ', $this->category));
    }

    public function getCategoryColorAttribute(): string
    {
        if ($this->relationLoaded('expenseCategory') && $this->expenseCategory) {
            return $this->expenseCategory->color;
        }
        $cat = ExpenseCategory::where('slug', $this->category)->first();
        return $cat ? $cat->color : '#f3f4f6';
    }

    public function getMonthNameAttribute(): string
    {
        return Payment::MONTHS[$this->month] ?? "Month {$this->month}";
    }

    public function scopeForYear($query, int $year)
    {
        return $query->whereHas('financialYear', fn ($q) => $q->where('year', $year));
    }
}
