<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Storefront email verification (SMTP link) was removed: the only sign-in
     * gate is now the admin approval on the "Pending Customers" page. Drop the
     * setting and treat every existing account as verified so nobody stays
     * locked out by a stale NULL.
     */
    public function up(): void
    {
        if (Schema::hasColumn('store_settings', 'require_email_verification')) {
            Schema::table('store_settings', function (Blueprint $table) {
                $table->dropColumn('require_email_verification');
            });
        }

        if (Schema::hasColumn('ecommerce_clients', 'email_verified_at')) {
            DB::table('ecommerce_clients')
                ->whereNull('email_verified_at')
                ->update(['email_verified_at' => now()]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('store_settings', 'require_email_verification')) {
            Schema::table('store_settings', function (Blueprint $table) {
                $table->boolean('require_email_verification')->default(true)->after('require_admin_approval');
            });
        }
    }
};
