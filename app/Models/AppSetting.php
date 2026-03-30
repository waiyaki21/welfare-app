<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class AppSetting extends Model
{
    protected $fillable = ['key', 'value'];

    // ── Core get/set/clear/reset ─────────────────────────────────────────────────────────
    // GET A SETTING
    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::rememberForever("app_setting_{$key}", function () use ($key, $default) {
            $setting = static::where('key', $key)->first();
            return $setting ? $setting->value : $default;
        });
    }
    // SET A SETTING
    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget("app_setting_{$key}");
    }

    // CLEAR/DELETE A SPECIFIC SETTING
    public static function clear(string $key): void
    {
        static::where('key', $key)->delete();

        // clear cache for that key
        Cache::forget("app_setting_{$key}");
    }

    // RESET ALL SETTINGS
    public static function reset(): void
    {
        static::query()->delete();

        // clear ALL cached settings
        Cache::flush();
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

    public static function importState(string $type): array
    {
        $map = [
            'year'        => 'import_yearly_enabled',
            'month'       => 'import_monthly_enabled',
            'expenditure' => 'import_expenditure_enabled',
        ];

        $key = $map[$type] ?? null;

        if (!$key) {
            return [
                'enabled' => false,
                'has_last_upload' => false,
                'last_upload' => null,
            ];
        }

        // force year to always be enabled
        $enabled = $type === 'year'
            ? true
            : static::canImport($key);

        // get last upload
        $lastUpload = static::getLastUpload($type);

        return [
            'enabled' => $enabled,
            'has_last_upload' => $lastUpload !== null,
            'last_upload' => $lastUpload,
        ];
    }

    /** Whether the spreadsheet import is enabled */
    public static function monthlyImportEnabled(): bool
    {
        return static::importState('month')['enabled'];
    }

    public static function expenditureImportEnabled(): bool
    {
        return static::importState('expenditure')['enabled'];
    }

    public static function yearlyImportEnabled(): bool
    {
        return static::importState('year')['enabled'];
    }

    /** Helper to check financial year and specific setting */
    protected static function canImport(string $key): bool
    {
        if (!\App\Models\FinancialYear::exists()) {
            return false;
        }

        return static::getBool($key, true);
    }

    // last upload checks
    public static function hasLastUpload(string $type): bool
    {
        return static::getLastUpload($type) !== null;
    }

    public static function getLastUpload(string $type): ?array
    {
        $key = "last_{$type}_upload";
        $data = static::get($key);

        return $data ? json_decode($data, true) : null;
    }
}
