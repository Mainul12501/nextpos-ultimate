<?php

namespace App\Console;

use App\Jobs\Shopify\ShopifySyncJob;
use App\Services\WooCommerce\SyncQueue;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
        'App\Console\Commands\DatabaseBackUp',
        'App\Console\Commands\WooCommerceSync',
        'App\\Console\\Commands\\WooCommercePushProducts',
        'App\Console\Commands\ShopifySync',
        'App\Console\Commands\SendMeetingReminders',
        'App\Console\Commands\ProcessScheduledCampaigns',
    ];

    /**
     * Define the application's command schedule.
     *
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {

        $schedule->command('database:backup');

        $schedule->command('assets:check-validation-due')->daily();

        // Rental: flip booked items to rented as contracts start, close renewed ones.
        $schedule->command('rental:sync-statuses')->hourly()->withoutOverlapping();
        // Rental: rent due / overdue / ending / return-due reminders, once a day.
        $schedule->command('rental:send-reminders')->dailyAt('09:00')->withoutOverlapping();
        $schedule->command('crm:send-reminders')->everyFiveMinutes()->withoutOverlapping();
        $schedule->command('crm:daily')->dailyAt('08:00')->withoutOverlapping();
        // Smart reordering: daily digest, sent once at/after the hour set in its settings.
        $schedule->command('reorder:digest')->hourly()->withoutOverlapping();

        $schedule->command('meetings:send-reminders')->everyMinute()->withoutOverlapping();

        $schedule->command('marketing:process-scheduled')->everyMinute()->withoutOverlapping();

        // Telegram: queued notifications (failed sends being retried, events
        // raised outside a web request). Drains its own 'telegram' queue, so it
        // can equally be given a cron entry of its own: telegram:send.
        $schedule->command('telegram:send')->everyMinute()->withoutOverlapping();

        // Keep integration OAuth connections alive: Salla refresh tokens die
        // after 1 month idle, Xero's after 60 days. A daily authenticated ping
        // refreshes them long before either cliff.
        $schedule->command('integrations:keep-alive')->daily()->withoutOverlapping();

        // Nightly Google Sheets snapshot when auto-export is enabled (kept off
        // peak hours; full-tab rewrites can take a while on big datasets).
        $schedule->command('google-sheets:auto-export')->dailyAt('02:30')->withoutOverlapping();

        // Courier status sync: shipments still on the way are checked against
        // their courier (the Courier Settings interval decides which are due).
        // Webhooks are the fast path; this catches everything else.
        $schedule->command('couriers:sync')->everyFifteenMinutes()->withoutOverlapping(30);

        // WooCommerce push (Stocky -> Woo): push only not-yet-linked products
        // nightly; stock changes often, so sync it hourly.
        $schedule->command('woocommerce:sync --scope=products --only-unsynced')
            ->dailyAt('02:00')
            ->withoutOverlapping();

        $schedule->command('woocommerce:sync --scope=stock')
            ->hourly()
            ->withoutOverlapping();

        // WooCommerce pull (Woo -> Stocky). Orders run often so online sales land in
        // Stocky quickly and are incremental (only orders changed since the last run).
        // Products and customers are full scans, so keep them to once a night.
        $schedule->command('woocommerce:sync --scope=orders')
            ->everyFifteenMinutes()
            ->withoutOverlapping(30);

        $schedule->command('woocommerce:sync --scope=pull-products')
            ->dailyAt('03:00')
            ->withoutOverlapping(120);

        $schedule->command('woocommerce:sync --scope=customers')
            ->dailyAt('03:30')
            ->withoutOverlapping(120);

        // Shopify. Unlike WooCommerce there is no fixed timetable here: each
        // connected shop carries its own plan (which entities, which direction,
        // how often), so this only has to tick often enough to honour the
        // smallest interval the UI allows — five minutes. The command works out
        // what is actually due and does nothing on the ticks where that is
        // nothing. The overlap lock is generous because a first catalogue push
        // can run for a long time, and starting a second one alongside it would
        // just burn the same shop's API rate limit twice.
        $schedule->command('shopify:sync')
            ->everyFiveMinutes()
            ->withoutOverlapping(120);

        /**
         * Shared hosting friendly queue processing:
         * Drain the queue each minute (database driver) instead of running a daemon.
         *
         * IMPORTANT: You must have a cron that runs `php artisan schedule:run` every minute.
         * Without it, Woo sync batches stall at "queued_next_batch" and only advance while
         * someone keeps the WooCommerce settings page open (it ticks one batch per poll).
         */
        // 'webhooks' carries outgoing webhooks, Slack/Telegram notifications and
        // the Mailchimp/Xero auto-sync jobs — it must drain here too, or none of
        // them ever run on cron-only (no Supervisor) hosting.
        $schedule->command('queue:work database --once --queue=default,webhooks --sleep=1 --tries=1 --timeout='.((int) env('QUEUE_WORKER_TIMEOUT', 1200)))
            ->everyMinute()
            ->withoutOverlapping()
            ->evenInMaintenanceMode();

        // Woo batches re-dispatch themselves one slice at a time, so --once would move a
        // sync forward by a single batch per minute. --stop-when-empty + --max-time keeps
        // draining for most of the minute and then exits, which the next tick restarts.
        $schedule->command('queue:work database --queue='.SyncQueue::NAME.' --stop-when-empty --max-time=55 --sleep=1 --tries=1 --timeout='.((int) env('QUEUE_WORKER_TIMEOUT', 1200)))
            ->everyMinute()
            ->withoutOverlapping(10)
            ->evenInMaintenanceMode();

        // Shopify syncs started from the Sync Centre run here, one slice per job
        // (ShopifySyncJob re-queues itself until the run is done) — same shape as
        // the Woo worker above. Without it a sync still finishes, but only while
        // the Sync Centre page stays open to carry it on.
        $schedule->command('queue:work database --queue='.ShopifySyncJob::QUEUE.' --stop-when-empty --max-time=55 --sleep=1 --tries=1 --timeout=600')
            ->everyMinute()
            ->withoutOverlapping(15)
            ->evenInMaintenanceMode();

    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
