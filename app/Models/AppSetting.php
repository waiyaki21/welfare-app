<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class AppSetting extends Model
{
    protected $fillable = ['key', 'value'];

    // ── Core get/set ─────────────────────────────────────────────────────────

    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::rememberForever("app_setting_{$key}", function () use ($key, $default) {
            $setting = static::where('key', $key)->first();
            return $setting ? $setting->value : $default;
        });
    }

    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget("app_setting_{$key}");
    }

    public static function getBool(string $key, bool $default = true): bool
    {
        $val = static::get($key, null);
        if ($val === null) return $default;
        return filter_var($val, FILTER_VALIDATE_BOOLEAN);
    }

    // ── App appearance ────────────────────────────────────────────────────────

    public static function appName(): string
    {
        return static::get('app_name', 'Athoni Welfare');
    }

    public static function appSubtitle(): string
    {
        return static::get('app_subtitle', 'Association Ledger');
    }

    public static function sidebarColor(): string
    {
        return static::get('sidebar_color', '#1a3a2a');
    }

    public static function theme(): string
    {
        // 'light' | 'dark' | 'system'  — reserved for future use
        return static::get('theme', 'light');
    }

    // ── Import settings ───────────────────────────────────────────────────────

    /** Whether the full-year spreadsheet import is enabled */
    public static function yearlyImportEnabled(): bool
    {
        return static::getBool('import_yearly_enabled', true);
    }

    /** Whether the monthly payments/welfare import is enabled */
    public static function monthlyImportEnabled(): bool
    {
        return static::getBool('import_monthly_enabled', true);
    }
}
