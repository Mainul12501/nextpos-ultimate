<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Superseded by WooCommerceAutoSyncJob, which pushes products as well as
 * stock and never runs two pushes at once. Kept so that jobs of this class
 * already waiting in the queue when the app is updated still run: they hand
 * their ids over.
 */
class WooCommerceInstantStockJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 1200;

    /** @param array<int, int> $productIds */
    public function __construct(public array $productIds = []) {}

    public function handle(): void
    {
        if ($this->productIds) {
            (new WooCommerceAutoSyncJob([], $this->productIds))->handle();
        }
    }
}
