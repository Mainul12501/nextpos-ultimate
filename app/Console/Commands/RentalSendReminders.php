<?php

namespace App\Console\Commands;

use App\Services\Rental\RentalReminderService;
use Illuminate\Console\Command;

/**
 * Daily rental reminders (rent due, overdue, contract ending, return due) over
 * the channels switched on in Rental Settings → Reminders.
 */
class RentalSendReminders extends Command
{
    protected $signature = 'rental:send-reminders';

    protected $description = 'Send rental customer reminders (rent due, overdue, contract ending, return due)';

    public function handle(RentalReminderService $reminders): int
    {
        $sent = $reminders->run();
        $this->info("{$sent} rental reminder(s) sent.");

        return self::SUCCESS;
    }
}
