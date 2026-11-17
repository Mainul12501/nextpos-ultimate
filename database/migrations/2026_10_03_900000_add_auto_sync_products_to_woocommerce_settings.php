<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WooCommerce: opt-in automatic push of a product created, edited or
 * duplicated in the POS (stock already has instant_stock_push).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('woocommerce_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('woocommerce_settings', 'auto_sync_products')) {
                $table->boolean('auto_sync_products')->default(false)->after('instant_stock_push');
            }
        });
    }

    public function down(): void
    {
        Schema::table('woocommerce_settings', function (Blueprint $table) {
            if (Schema::hasColumn('woocommerce_settings', 'auto_sync_products')) {
                $table->dropColumn('auto_sync_products');
            }
        });
    }
};
