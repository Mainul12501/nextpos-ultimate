<?php

namespace Database\Seeders;

use App\Services\StoreLanguageInstaller;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds storefront translations for INSTALLED store languages only.
 *
 * Runs from the migration that creates store_translations (fresh install /
 * first upgrade: en, fr, ar, es) and after every upgrade (PostUpdateTasks), so
 * new storefront keys reach installed languages. Optional packs are installed
 * on demand from Store → Languages; admin-edited rows are always preserved.
 */
class StoreTranslationSeeder extends Seeder
{
    public function run(): void
    {
        DB::disableQueryLog();

        $skipped = (new StoreLanguageInstaller)->seedInstalled();

        if ($skipped > 0) {
            $this->command?->info("Preserved {$skipped} customized storefront translation(s).");
        }
    }
}
