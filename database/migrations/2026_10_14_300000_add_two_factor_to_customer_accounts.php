<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional two-factor sign-in for customers: online store accounts
 * (ecommerce_clients) and client portal logins (portal_clients). Same columns
 * as users, plus the method each customer picked: 'app' (authenticator) or
 * 'email' (a code sent at sign-in).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['ecommerce_clients', 'portal_clients'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (! Schema::hasColumn($tableName, 'two_factor_method')) {
                    $table->string('two_factor_method', 10)->nullable();
                }
                if (! Schema::hasColumn($tableName, 'two_factor_secret')) {
                    $table->text('two_factor_secret')->nullable();
                }
                if (! Schema::hasColumn($tableName, 'two_factor_recovery_codes')) {
                    $table->text('two_factor_recovery_codes')->nullable();
                }
                if (! Schema::hasColumn($tableName, 'two_factor_confirmed_at')) {
                    $table->timestamp('two_factor_confirmed_at')->nullable();
                }
            });
        }

        // Off by default: the store owner opts in (Store Settings).
        if (Schema::hasTable('store_settings') && ! Schema::hasColumn('store_settings', 'customer_two_factor_enabled')) {
            Schema::table('store_settings', function (Blueprint $table) {
                $table->boolean('customer_two_factor_enabled')->default(false);
            });
        }
    }

    public function down(): void
    {
        foreach (['ecommerce_clients', 'portal_clients'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                foreach (['two_factor_method', 'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at'] as $column) {
                    if (Schema::hasColumn($tableName, $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('store_settings') && Schema::hasColumn('store_settings', 'customer_two_factor_enabled')) {
            Schema::table('store_settings', function (Blueprint $table) {
                $table->dropColumn('customer_two_factor_enabled');
            });
        }
    }
};
