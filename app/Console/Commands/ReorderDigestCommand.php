<?php

namespace App\Console\Commands;

use App\Services\Reorder\ReorderDigest;
use Illuminate\Console\Command;

/**
 * Daily "products need reordering" email (Purchases → Reorder suggestions →
 * Settings). Scheduled hourly; it sends once a day at or after the chosen
 * hour. Cron for this module alone:
 *   0 * * * * php /path/to/artisan reorder:digest
 */
class ReorderDigestCommand extends Command
{
    protected $signature = 'reorder:digest {--force : Send now, even if already sent today or before the hour}';

    protected $description = 'Email the daily reorder digest to the chosen staff (once a day)';

    public function handle(): int
    {
        $sent = ReorderDigest::sendIfDue((bool) $this->option('force'));
        $this->info($sent ? "Reorder digest sent to {$sent} recipient(s)." : 'Nothing to send (not due, no recipients or nothing to reorder).');

        return self::SUCCESS;
    }
}
