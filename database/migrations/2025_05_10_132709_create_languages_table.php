<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('languages', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->integer('id', true);
            $table->string('name');             // e.g. "French"
            $table->string('locale')->unique(); // e.g. "fr", "en", "ar"
            $table->string('flag')->nullable(); // e.g. "fr.png"
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps(6);
        });

        // Only the four default packs are installed on a fresh install; every
        // other pack in LanguageCatalog is installed on demand from
        // Settings → Languages. (Existing installs ran this migration long ago
        // and keep whatever languages they already have.)
        DB::table('languages')->insert(\App\Support\LanguageCatalog::defaultLanguageRows());

        // Get default_language from settings table
        $defaultLocale = DB::table('settings')->value('default_language') ?? 'en';

        // Reset all is_default flags
        DB::table('languages')->update(['is_default' => false]);

        // Set is_default to true for the matching locale
        $matched = DB::table('languages')->where('locale', $defaultLocale)->update(['is_default' => true]);
        if (! $matched) {
            DB::table('languages')->where('locale', 'en')->update(['is_default' => true]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('languages');
    }
};
