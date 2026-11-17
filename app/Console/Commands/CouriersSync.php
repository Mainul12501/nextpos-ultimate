<?php

namespace App\Console\Commands;

use App\Services\Couriers\CourierLogger;
use App\Services\Couriers\ShipmentSync;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Courier status sync: shipments still on the way are checked against their
 * courier (the Courier Settings interval decides what is due). Runs every
 * 15 minutes from the scheduler.
 */
class CouriersSync extends Command
{
    protected $signature = 'couriers:sync {--limit=200 : Max shipments per run}';

    protected $description = 'Fetch courier status updates for shipments still on the way';

    public function handle(): int
    {
        try {
            if (! Schema::hasTable('courier_accounts') || ! Schema::hasColumn('shipments', 'sync_failures')) {
                return self::SUCCESS;
            }
            // Couriers → Logs keeps 30 days.
            CourierLogger::prune();

            $sync = app(ShipmentSync::class);
            $due = $sync->due((int) $this->option('limit'));
            $changed = $failed = 0;
            foreach ($due as $shipment) {
                $r = $sync->syncOne($shipment);
                $failed += $r['ok'] ? 0 : 1;
                $changed += $r['changed'] ? 1 : 0;
            }

            if ($due->count()) {
                $this->line("{$due->count()} checked, {$changed} updated, {$failed} failed");
            }
        } catch (\Throwable $e) {
            $this->warn($e->getMessage());
            report($e);
        }

        return self::SUCCESS;
    }
}
