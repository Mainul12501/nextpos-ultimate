<?php

namespace App\Console\Commands;

use App\Services\Crm\CrmReminderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/** Sends due CRM task reminders (email / push). Scheduled every five minutes. */
class CrmSendReminders extends Command
{
    protected $signature = 'crm:send-reminders';

    protected $description = 'Send reminders for CRM tasks and planned activities that are coming due';

    public function handle(CrmReminderService $reminders): int
    {
        if (! Schema::hasTable('crm_activities')) {
            return self::SUCCESS;
        }
        $sent = $reminders->run();
        $this->info("CRM reminders sent: {$sent}");

        return self::SUCCESS;
    }
}
