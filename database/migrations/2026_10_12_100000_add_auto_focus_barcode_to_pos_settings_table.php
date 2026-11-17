<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds the `auto_focus_barcode` toggle to pos_settings: keeps the POS
     * scan/search field focused so handheld scanners always type into it,
     * and focuses the product search on the back-office document forms.
     * Defaults ON so existing installs get the feature after upgrade.
     */
    public function up(): void
    {
        Schema::table('pos_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('pos_settings', 'auto_focus_barcode')) {
                $table->boolean('auto_focus_barcode')->default(1);
            }
        });
    }

    public function down(): void
    {
        Schema::table('pos_settings', function (Blueprint $table) {
            if (Schema::hasColumn('pos_settings', 'auto_focus_barcode')) {
                $table->dropColumn('auto_focus_barcode');
            }
        });
    }
};
