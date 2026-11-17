<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a scheduled Shopify sync actually runs.
 *
 * `auto_sync` and `sync_interval_minutes` already said *whether* and *how
 * often*, but never *what* — and guessing is not safe here: a nightly
 * products push and a nightly products pull are opposite operations, and
 * picking the wrong one rewrites either the shop's catalogue or the ERP's.
 *
 * So the plan is explicit: an ordered list of {entity, direction, every}.
 * Null means "use the built-in default" (pull orders, push inventory), which
 * is the pair no shop is surprised by.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('shopify_stores', function (Blueprint $table) {
            if (! Schema::hasColumn('shopify_stores', 'auto_sync_plan')) {
                $table->json('auto_sync_plan')->nullable()->after('sync_interval_minutes');
            }
        });
    }

    public function down()
    {
        Schema::table('shopify_stores', function (Blueprint $table) {
            if (Schema::hasColumn('shopify_stores', 'auto_sync_plan')) {
                $table->dropColumn('auto_sync_plan');
            }
        });
    }
};
