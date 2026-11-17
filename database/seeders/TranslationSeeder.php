<?php

namespace Database\Seeders;

use App\Services\LanguageInstaller;
use App\Support\LanguageCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seeds translation packs for INSTALLED languages only (rows in `languages`).
 *
 * Runs on a fresh install (from the migration that creates `translations`,
 * when only the four default languages exist) and after every upgrade
 * (PostUpdateTasks). Optional packs are never pulled in here — they are
 * installed on demand from Settings → Languages — and a language the admin
 * deleted is not brought back. Client-customized rows are always preserved.
 */
class TranslationSeeder extends Seeder
{
    public function run(): void
    {
        // Disable query logging for speed & memory
        DB::disableQueryLog();

        $installer = new LanguageInstaller;
        $skipped = 0;

        foreach ($this->installedLocales() as $locale) {
            $path = LanguageCatalog::packPath($locale);
            if (! $path) {
                // Admin-created language with no shipped pack — nothing to seed.
                continue;
            }
            $skipped += $installer->importLocale($locale, $path);
        }

        if ($skipped > 0) {
            $this->command?->info("Preserved {$skipped} client-customized translation(s).");
        }
    }

    protected function installedLocales(): array
    {
        if (! Schema::hasTable('languages')) {
            return LanguageCatalog::DEFAULT_LOCALES;
        }

        $locales = DB::table('languages')->pluck('locale')->all();

        // English is the fallback for every other locale — always keep it seeded.
        if (! in_array('en', $locales, true)) {
            $locales[] = 'en';
        }

        return $locales;
    }
}
