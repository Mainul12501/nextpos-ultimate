<?php

namespace App\Services;

use App\Support\LanguageCatalog;
use Illuminate\Support\Facades\DB;

/**
 * Back-office (admin app) languages exposed through the shared Languages /
 * Translations admin API. Unlike the store and portal surfaces these use the
 * original `languages` / `translations` tables and the packs in
 * database/seeders/translations/, and installing goes through the existing
 * LanguageInstaller (which also copies the email/SMS templates).
 *
 * The admin SPA reads its strings from GET /api/translations/{locale} and
 * Blade from tdb(); neither goes through Laravel's translator, so there is no
 * group to serve and the runtime helpers of the base class are unused.
 */
class AdminLanguageInstaller extends ScopedLanguageInstaller
{
    public static function scope(): string
    {
        return 'admin';
    }

    public static function group(): string
    {
        return '';
    }

    public static function packDir(): string
    {
        return 'translations';
    }

    public static function languagesTable(): string
    {
        return 'languages';
    }

    public static function translationsTable(): string
    {
        return 'translations';
    }

    public static function packPath(string $locale): ?string
    {
        return LanguageCatalog::packPath($locale);
    }

    public static function availablePacks(): array
    {
        return LanguageCatalog::available();
    }

    public function install(string $locale): object
    {
        (new LanguageInstaller)->install($locale);

        return DB::table('languages')->where('locale', $locale)->first();
    }

    public function uninstall(string $locale): void
    {
        DB::table('translations')->where('locale', $locale)->delete();
        DB::table('languages')->where('locale', $locale)->delete();
        static::forget($locale);
    }

    /** The admin app caches nothing server-side except tdb()'s per-locale map. */
    public static function forget(?string $locale = null): void
    {
        if (function_exists('forget_db_translations')) {
            try {
                forget_db_translations($locale);
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }
}
