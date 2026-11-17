<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Push stock to WooCommerce the moment it changes, instead of waiting for the
 * hourly stock sync.
 *
 * Opt-in, like the Shopify one: it queues a job per stock movement, which pays
 * for itself in a shop that sells the same stock over a counter and is waste
 * in a shop whose stock only moves when Woo itself sells something.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('woocommerce_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('woocommerce_settings', 'instant_stock_push')) {
                $table->boolean('instant_stock_push')->default(false)->after('enable_auto_sync');
            }
        });
    }

    public function down()
    {
        Schema::table('woocommerce_settings', function (Blueprint $table) {
            if (Schema::hasColumn('woocommerce_settings', 'instant_stock_push')) {
                $table->dropColumn('instant_stock_push');
            }
        });
    }
};
