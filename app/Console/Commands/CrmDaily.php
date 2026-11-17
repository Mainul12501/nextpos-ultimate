<?php

namespace App\Console\Commands;

use App\Models\CrmSetting;
use App\Services\Crm\CrmScoringService;
use App\Services\Crm\CrmStaleAlertService;
use App\Services\Crm\CrmWeeklyReportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/** Daily CRM upkeep: re-score leads (time-based rules) and send the stale leads / stuck deals digest. */
class CrmDaily extends Command
{
    protected $signature = 'crm:daily {--force : Send the digest again even if it already went out today}';

    protected $description = 'Re-score CRM leads, send the stale leads / stuck deals digest and (Mondays) the weekly summary';

    public function handle(CrmScoringService $scoring, CrmStaleAlertService $alerts, CrmWeeklyReportService $weekly): int
    {
        if (! Schema::hasTable('crm_scoring_rules')) {
            return self::SUCCESS;
        }
        $changed = CrmSetting::current()->scoring_enabled ? $scoring->refreshAll() : 0;
        $sent = $alerts->run((bool) $this->option('force'));
        // Mondays only (or with --force).
        $weeklySent = Schema::hasColumn('crm_settings', 'weekly_report') ? $weekly->run((bool) $this->option('force')) : 0;
        $this->info("Lead scores changed: {$changed}; stale digests sent: {$sent}; weekly summaries sent: {$weeklySent}");

        return self::SUCCESS;
    }
}
