<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Push stock the moment it changes, instead of waiting for the next scheduled
 * inventory run.
 *
 * Opt-in per shop, because it only pays off where it is needed: a shop that
 * sells the same stock over a counter, where an hour of drift is an oversold
 * order. It queues one small job per stock change, so a shop with a busy POS
 * and a slow queue is better off leaving it alone and shortening the
 * scheduled interval instead.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('shopify_stores', function (Blueprint $table) {
            if (! Schema::hasColumn('shopify_stores', 'instant_stock_push')) {
                $table->boolean('instant_stock_push')->default(false)->after('auto_sync_plan');
            }
        });
    }

    public function down()
    {
        Schema::table('shopify_stores', function (Blueprint $table) {
            if (Schema::hasColumn('shopify_stores', 'instant_stock_push')) {
                $table->dropColumn('instant_stock_push');
            }
        });
    }
};
