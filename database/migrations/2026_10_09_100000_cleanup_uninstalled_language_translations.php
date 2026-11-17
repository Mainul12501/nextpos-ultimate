<?php

use App\Support\LanguageCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Translation packs are now installed per language (LanguageCatalog). Two
     * clean-ups for existing installs:
     *
     * 1. The Swahili and Amharic packs (ke.php / et.php) used to be seeded under
     *    the file name ('ke' / 'et') while their languages rows use 'sw' / 'am',
     *    so those languages showed no translations. Move the rows across.
     * 2. The old seeder imported EVERY pack on each upgrade, re-creating rows for
     *    languages the admin had deleted. Drop pack rows for catalog languages
     *    that are not installed (customized rows are kept).
     */
    public function up(): void
    {
        if (! Schema::hasTable('translations') || ! Schema::hasTable('languages')) {
            return;
        }

        $installed = DB::table('languages')->pluck('locale')
            ->map(fn ($l) => strtolower($l))->flip()->all();

        foreach (['ke' => 'sw', 'et' => 'am'] as $legacy => $locale) {
            if (isset($installed[$legacy])) {
                continue; // admin really has a language with this code — leave it
            }
            if (isset($installed[$locale])) {
                DB::table('translations')->where('locale', $legacy)->orderBy('id')
                    ->chunk(1000, function ($rows) use ($locale) {
                        DB::table('translations')->insertOrIgnore($rows->map(fn ($r) => [
                            'locale' => $locale,
                            'key' => $r->key,
                            'value' => $r->value,
                            'is_default' => 0,
                            'created_at' => $r->created_at,
                            'updated_at' => $r->updated_at,
                        ])->all());
                    });
            }
            DB::table('translations')->where('locale', $legacy)->delete();
        }

        $hasCustomized = Schema::hasColumn('translations', 'is_customized');
        foreach (array_keys(LanguageCatalog::all()) as $locale) {
            // English is every locale's fallback and is always seeded.
            if ($locale === 'en' || isset($installed[strtolower($locale)])) {
                continue;
            }
            DB::table('translations')
                ->where('locale', $locale)
                ->when($hasCustomized, fn ($q) => $q->where('is_customized', 0))
                ->delete();
        }
    }

    public function down(): void
    {
        // Data clean-up only; packs can be re-installed from Settings → Languages.
    }
};
