<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit log: who changed what and when, with before/after values. Rows are
 * written by App\Services\Audit\AuditLogger (model events + the Auditable
 * builder for mass updates) and never edited afterwards.
 *
 * settings.audit_log_enabled switches recording off; audit_log_retention_days
 * (NULL = keep forever) is applied by the daily audit:prune command.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('user_name', 191)->nullable();
                $table->string('event', 40)->index();
                $table->string('auditable_type', 191)->nullable();
                $table->unsignedBigInteger('auditable_id')->nullable();
                $table->string('auditable_label', 191)->nullable();
                $table->longText('old_values')->nullable();
                $table->longText('new_values')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->string('user_agent', 255)->nullable();
                $table->string('url', 500)->nullable();
                $table->string('method', 10)->nullable();
                $table->string('batch_id', 36)->nullable()->index();
                $table->timestamp('created_at')->nullable()->index();

                $table->index(['auditable_type', 'auditable_id']);
            });
        }

        Schema::table('settings', function (Blueprint $table) {
            if (! Schema::hasColumn('settings', 'audit_log_enabled')) {
                $table->boolean('audit_log_enabled')->default(true);
            }
            if (! Schema::hasColumn('settings', 'audit_log_retention_days')) {
                $table->unsignedInteger('audit_log_retention_days')->nullable()->default(365);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');

        Schema::table('settings', function (Blueprint $table) {
            foreach (['audit_log_enabled', 'audit_log_retention_days'] as $column) {
                if (Schema::hasColumn('settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
