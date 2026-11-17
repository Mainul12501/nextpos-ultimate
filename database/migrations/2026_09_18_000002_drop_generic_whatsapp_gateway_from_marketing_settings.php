<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The generic "WhatsApp HTTP gateway" fields on marketing_settings are
 * replaced by the dedicated Meta Cloud API integration (whatsapp_settings,
 * Integrations → WhatsApp). Drop the old columns.
 */
return new class extends Migration
{
    private const COLUMNS = [
        'whatsapp_enabled', 'whatsapp_provider', 'whatsapp_api_url', 'whatsapp_api_key',
        'whatsapp_phone_id', 'whatsapp_http_method', 'whatsapp_extra_params',
        'whatsapp_to_field', 'whatsapp_message_field',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('marketing_settings')) {
            return;
        }
        $drop = array_values(array_filter(self::COLUMNS, fn ($c) => Schema::hasColumn('marketing_settings', $c)));
        if ($drop) {
            Schema::table('marketing_settings', function (Blueprint $table) use ($drop) {
                $table->dropColumn($drop);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('marketing_settings') || Schema::hasColumn('marketing_settings', 'whatsapp_api_url')) {
            return;
        }
        Schema::table('marketing_settings', function (Blueprint $table) {
            $table->boolean('whatsapp_enabled')->default(false);
            $table->string('whatsapp_provider')->nullable();
            $table->string('whatsapp_api_url')->nullable();
            $table->string('whatsapp_api_key')->nullable();
            $table->string('whatsapp_phone_id')->nullable();
            $table->string('whatsapp_http_method')->default('POST');
            $table->json('whatsapp_extra_params')->nullable();
            $table->string('whatsapp_to_field')->default('to');
            $table->string('whatsapp_message_field')->default('message');
        });
    }
};
