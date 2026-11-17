<?php

namespace App\Console\Commands;

use App\Services\Audit\AuditLogger;
use Illuminate\Console\Command;

/**
 * Manual run of the audit log retention cleanup. Not scheduled: the cleanup
 * already runs by itself once a day (AuditLogger::schedulePrune), so no cron
 * is needed — this is only for pruning on demand or with a custom window.
 */
class AuditPrune extends Command
{
    protected $signature = 'audit:prune {--days= : Override the retention setting for this run}';

    protected $description = 'Delete audit log entries older than the retention window';

    public function handle(): int
    {
        $days = $this->option('days');
        $deleted = AuditLogger::prune($days === null ? null : (int) $days, PHP_INT_MAX);

        $this->info("Pruned {$deleted} audit log entries.");

        return self::SUCCESS;
    }
}
