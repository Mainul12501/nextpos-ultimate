<?php

use App\Support\LanguageCatalog;
use Database\Seeders\StoreTranslationSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Storefront languages + their translations, independent from the admin
     * `languages` / `translations` tables so the store and the back office can
     * offer different language sets.
     *
     * Only the four default packs (en, fr, ar, es — the ones that ship as
     * resources/lang/<locale>/messages.php) are installed here, on fresh
     * installs and upgrades alike. Every other catalog language is installed
     * on demand from Store → Languages. The store's default language stays in
     * store_settings.language.
     */
    public function up(): void
    {
        if (! Schema::hasTable('store_languages')) {
            Schema::create('store_languages', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->increments('id');
                $table->string('name');
                $table->string('locale', 20)->unique();
                $table->string('flag')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps(6);
            });
        }

        if (! Schema::hasTable('store_translations')) {
            Schema::create('store_translations', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->increments('id');
                $table->string('locale', 20);
                // Keys are case-sensitive ('Cart' vs 'cart' are different keys).
                $table->string('key')->collation('utf8mb4_bin');
                $table->text('value');
                // Set when the admin edits a row; re-seeding never overwrites it.
                $table->boolean('is_customized')->default(false);
                $table->timestamps(6);

                $table->unique(['locale', 'key']);
                $table->index('locale');
            });
        }

        if (DB::table('store_languages')->count() === 0) {
            $now = now();
            DB::table('store_languages')->insert(array_map(fn ($row) => [
                'name' => LanguageCatalog::nativeName($row['locale']),
                'locale' => $row['locale'],
                'flag' => $row['flag'],
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ], LanguageCatalog::defaultLanguageRows()));
        }

        (new StoreTranslationSeeder)->run();
    }

    public function down(): void
    {
        Schema::dropIfExists('store_translations');
        Schema::dropIfExists('store_languages');
    }
};
