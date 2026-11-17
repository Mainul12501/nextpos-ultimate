<?php

namespace App\Console\Commands;

use App\Services\Rental\RentalBillingService;
use App\Services\Rental\RentalContractService;
use App\Services\Rental\RentalMaintenanceService;
use Illuminate\Console\Command;

/**
 * Keeps rental item statuses in step with the clock: a booked item flips to
 * "rented" when its contract starts, renewed contracts close at their end
 * date. The admin pages also sync on load, so this only matters for screens
 * nobody opens (e.g. the storefront later) — hourly is plenty.
 */
class RentalSyncStatuses extends Command
{
    protected $signature = 'rental:sync-statuses';

    protected $description = 'Sync rental statuses, issue due invoices, apply late fees and open due preventive maintenance';

    public function handle(RentalContractService $service, RentalBillingService $billing, RentalMaintenanceService $maintenance): int
    {
        $jobs = $maintenance->runSchedules();
        $service->sync();
        $billing->run();
        $this->info("Rental statuses synced, invoices issued, late fees applied, {$jobs} maintenance job(s) opened.");

        return self::SUCCESS;
    }
}
