<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Member extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'phone', 'joined_year', 'is_active', 'notes'];

    protected $casts = ['is_active' => 'boolean'];

    // ── Relationships ────────────────────────────────────────────────────────

    public function financials()
    {
        return $this->hasMany(MemberFinancial::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function welfareEvents()
    {
        return $this->hasMany(WelfareEvent::class);
    }

    // ── Scoped helpers ───────────────────────────────────────────────────────

    public function financialForYear(int $year): ?MemberFinancial
    {
        return $this->financials()
            ->whereHas('financialYear', fn ($q) => $q->where('year', $year))
            ->with('financialYear')
            ->first();
    }

    public function paymentsForYear(int $year)
    {
        return $this->payments()
            ->whereHas('financialYear', fn ($q) => $q->where('year', $year))
            ->orderBy('month');
    }

    // ── Accessors ────────────────────────────────────────────────────────────

    public function getInitialsAttribute(): string
    {
        $words = explode(' ', trim($this->name));
        return collect($words)->take(2)->map(fn ($w) => strtoupper($w[0]))->implode('') ?: '?';
    }

    public function getShortNameAttribute(): string
    {
        $parts = explode(' ', $this->name);
        return count($parts) >= 2 ? $parts[0] . ' ' . $parts[count($parts) - 1] : $this->name;
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
              ->orWhere('phone', 'like', "%{$term}%");
        });
    }
}
