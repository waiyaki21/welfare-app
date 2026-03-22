<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpenseCategory extends Model
{
    protected $fillable = ['slug', 'name', 'color', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    // ── Relationships ────────────────────────────────────────────────────────

    public function expenses()
    {
        return $this->hasMany(Expense::class, 'category', 'slug');
    }

    // ── Static helpers ───────────────────────────────────────────────────────

    /**
     * Return all categories as [slug => name] for dropdowns.
     */
    public static function forSelect(): array
    {
        return static::where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'slug')
            ->toArray();
    }

    /**
     * Find by slug or create a new category from an unknown import label.
     * The slug is auto-generated from the label (lowercased, spaces → underscores).
     */
    public static function findOrImport(string $slug, string $rawLabel = ''): self
    {
        return static::firstOrCreate(
            ['slug' => $slug],
            [
                'name'      => $rawLabel ?: ucwords(str_replace('_', ' ', $slug)),
                'color'     => '#f3f4f6',
                'is_active' => true,
            ]
        );
    }

    /**
     * Turn a raw spreadsheet label into a clean slug.
     * e.g. "BANK / MPESA CHARGES" → "bank_mpesa_charges"
     */
    public static function slugify(string $label): string
    {
        $slug = strtolower(trim($label));
        $slug = preg_replace('/[^a-z0-9]+/', '_', $slug);
        $slug = trim($slug, '_');
        return substr($slug, 0, 50); // match column length
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
