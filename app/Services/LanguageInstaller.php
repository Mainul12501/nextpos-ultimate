<?php

namespace App\Services;

use App\Models\Language;
use App\Support\LanguageCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Installs a translation pack from LanguageCatalog and (re)imports its keys.
 *
 * Shared by TranslationSeeder (install/upgrade, installed locales only) and the
 * Languages page "Install" button. Importing is idempotent: keys are upserted,
 * and rows the admin edited through the Translations UI (is_customized) are
 * never overwritten.
 */
class LanguageInstaller
{
    /**
     * Install a catalog language: create its `languages` row (active), import
     * its pack and give it a copy of the English email/SMS templates. Safe to
     * re-run on an already-installed language — it only fills what is missing.
     */
    public function install(string $locale): Language
    {
        $entry = LanguageCatalog::find($locale);
        if (! $entry || ! is_file($entry['path'])) {
            throw new \InvalidArgumentException("No translation pack is available for '{$locale}'.");
        }

        $language = Language::where('locale', $locale)->first();
        if (! $language) {
            $language = Language::create([
                'name' => $entry['name'],
                'locale' => $locale,
                'flag' => $entry['flag'],
                'is_active' => true,
                'is_default' => false,
            ]);
        }

        $this->importLocale($locale, $entry['path']);
        $this->copyMessageTemplates($locale);

        if (function_exists('forget_db_translations')) {
            forget_db_translations($locale);
        }

        return $language;
    }

    /**
     * Upsert one pack into `translations` under $locale. Returns the number of
     * customized rows that were preserved.
     */
    public function importLocale(string $locale, string $path): int
    {
        $translations = require $path;
        if (! is_array($translations)) {
            return 0;
        }

        // On a fresh install this runs from inside the migration that creates
        // the table; is_customized is part of that create, but older installs
        // only got it later — guard the lookup either way.
        $customized = [];
        if (Schema::hasColumn('translations', 'is_customized')) {
            $customized = DB::table('translations')
                ->where('locale', $locale)
                ->where('is_customized', 1)
                ->pluck('key')
                ->flip()
                ->all();
        }

        $now = now();
        $rows = [];
        $skipped = 0;

        foreach ($translations as $key => $value) {
            // Keys are compared case-sensitively (the column is utf8mb4_bin).
            if (isset($customized[$key])) {
                $skipped++;

                continue;
            }
            $rows[] = [
                'locale' => $locale,
                'key' => $key,
                'value' => $value,
                'is_default' => $locale === 'en' ? 1 : 0,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 1000) as $chunk) {
            DB::table('translations')->upsert(
                $chunk,
                ['locale', 'key'],
                ['value', 'is_default', 'updated_at']
            );
        }

        return $skipped;
    }

    /**
     * Templates are stored per locale; a language installed after the initial
     * setup would otherwise have none. Copies only the ones it is missing.
     */
    public function copyMessageTemplates(string $locale): void
    {
        if ($locale === 'en') {
            return;
        }

        $tables = [
            'email_messages' => ['subject', 'body'],
            'sms_messages' => ['text'],
        ];

        $now = now();
        foreach ($tables as $table => $columns) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'locale')) {
                continue;
            }

            $existing = DB::table($table)
                ->where('locale', $locale)
                ->whereNull('deleted_at')
                ->pluck('name')
                ->flip();

            $english = DB::table($table)
                ->where('locale', 'en')
                ->whereNull('deleted_at')
                ->get(array_merge(['name'], $columns));

            foreach ($english as $row) {
                if (isset($existing[$row->name])) {
                    continue;
                }
                $insert = ['name' => $row->name, 'locale' => $locale, 'created_at' => $now, 'updated_at' => $now];
                foreach ($columns as $col) {
                    $insert[$col] = $row->{$col};
                }
                DB::table($table)->insert($insert);
            }
        }
    }
}
