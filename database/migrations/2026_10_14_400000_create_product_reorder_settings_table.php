<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Smart reordering: optional per-product overrides (any null column falls
 * back to the global defaults in settings.reorder_settings).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product_reorder_settings')) {
            Schema::create('product_reorder_settings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('product_id')->unique();
                // Supplier to order from; null = the last supplier it was bought from.
                $table->unsignedBigInteger('preferred_provider_id')->nullable();
                $table->unsignedSmallInteger('lead_time_days')->nullable();
                $table->unsignedSmallInteger('safety_days')->nullable();
                // In the product's purchase unit.
                $table->decimal('min_order_qty', 15, 4)->nullable();
                $table->decimal('order_multiple', 15, 4)->nullable();
                // Never suggest this product (discontinued, made to order…).
                $table->boolean('excluded')->default(false);
                $table->timestamps();

                $table->index('preferred_provider_id');
            });
        }

        // Global defaults (JSON): sales_window_days, lead_time_days, safety_days, coverage_days.
        if (! Schema::hasColumn('settings', 'reorder_settings')) {
            Schema::table('settings', function (Blueprint $table) {
                $table->text('reorder_settings')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_reorder_settings');

        if (Schema::hasColumn('settings', 'reorder_settings')) {
            Schema::table('settings', function (Blueprint $table) {
                $table->dropColumn('reorder_settings');
            });
        }
    }
};
