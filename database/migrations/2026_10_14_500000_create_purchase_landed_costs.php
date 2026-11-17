<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Landed cost: extra costs of getting a purchase into stock (freight, customs,
 * insurance…) spread over its lines. Amounts are in the base currency, like
 * every stored amount. They don't change the purchase's GrandTotal (often
 * paid to other parties); each line keeps its share in landed_cost.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('purchase_landed_costs')) {
            Schema::create('purchase_landed_costs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('purchase_id')->index();
                $table->string('label', 120);
                $table->decimal('amount', 15, 4);
                // How it is spread over the lines: value | quantity | weight.
                $table->string('method', 10)->default('value');
                // Who charged it (freight forwarder, customs broker…), optional.
                $table->unsignedBigInteger('provider_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('purchase_details', 'landed_cost')) {
            Schema::table('purchase_details', function (Blueprint $table) {
                // This line's share of the purchase's landed costs (total, base currency).
                $table->decimal('landed_cost', 15, 4)->default(0);
            });
        }

        if (! Schema::hasColumn('purchases', 'landed_include_shipping')) {
            Schema::table('purchases', function (Blueprint $table) {
                // Also spread the purchase's shipping amount (by value).
                $table->boolean('landed_include_shipping')->default(false);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_landed_costs');

        if (Schema::hasColumn('purchase_details', 'landed_cost')) {
            Schema::table('purchase_details', fn (Blueprint $table) => $table->dropColumn('landed_cost'));
        }
        if (Schema::hasColumn('purchases', 'landed_include_shipping')) {
            Schema::table('purchases', fn (Blueprint $table) => $table->dropColumn('landed_include_shipping'));
        }
    }
};
