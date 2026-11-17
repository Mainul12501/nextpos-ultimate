<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * POS Theme (System Settings → POS Theme): the store-wide POS layout
     * (classic|touch|compact|split|cards) and colour mode (light|dark|auto).
     * Nullable so existing installs keep today's Classic Light look.
     * `pos_theme_device_override` lets each till pick its own theme from the
     * in-POS gear menu (stored per device in localStorage); default OFF.
     */
    public function up(): void
    {
        Schema::table('pos_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('pos_settings', 'pos_theme')) {
                $table->string('pos_theme', 32)->nullable();
            }
            if (!Schema::hasColumn('pos_settings', 'pos_color_mode')) {
                $table->string('pos_color_mode', 8)->nullable();
            }
            if (!Schema::hasColumn('pos_settings', 'pos_theme_device_override')) {
                $table->boolean('pos_theme_device_override')->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('pos_settings', function (Blueprint $table) {
            foreach (['pos_theme', 'pos_color_mode', 'pos_theme_device_override'] as $column) {
                if (Schema::hasColumn('pos_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
