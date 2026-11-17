<?php

namespace App\Jobs;

use App\Models\WooCommerceLog;
use App\Models\WooCommerceSetting;
use App\Services\WooCommerce\Client;
use App\Services\WooCommerce\StockWatcher;
use App\Services\WooCommerce\SyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

/**
 * Pushes products and/or their stock to WooCommerce, dispatched by
 * StockWatcher when something changed in the POS.
 *
 * The hourly `--scope=stock` run walks every linked product; this one touches
 * only what moved, which is what makes it cheap enough to run off a sale. It
 * reuses SyncService::pushProducts() / syncStock() with an id filter rather
 * than a second copy of the logic, so variable products, combos and services
 * behave here exactly as they do in the full runs.
 *
 * Every job first adds its ids to a pending list, then drains that list if no
 * other job is already doing so. A job that finds another one running leaves
 * its ids to it and returns at once, so:
 *
 *  - pushes never run side by side (two quick edits of a new product cannot
 *    both create it; an older stock level cannot land after a newer one);
 *  - nothing waits on a lock — this usually runs after the response inside a
 *    PHP worker, which must not sit idle while another push finishes;
 *  - nothing is dropped because a push was already running.
 *
 * If a round fails entirely and the store does not answer, the ids go back on
 * the list and the pushes pause for a few minutes, so a store that is down
 * does not cost every sale a string of timeouts. The first change after the
 * pause sends everything that was waiting (and the hourly stock run catches up
 * regardless).
 *
 * Carries ids, never quantities: the level is read when the job runs. Settings
 * are re-read here too, so switching a push off also stops work that was
 * already waiting.
 */
class WooCommerceAutoSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Products per round; the run lock is renewed between rounds. */
    private const ROUND = 50;

    /** How long a run may hold the store before another job may take over. */
    private const RUN_LOCK_SECONDS = 600;

    /** Pause after the store stopped answering. */
    private const PAUSE_SECONDS = 300;

    private const PENDING_TTL = 604800;

    public int $timeout = 1200;
    public int $tries = 1;

    /**
     * @param  int[]  $productIds  pushed in full (details, variants, images), then their stock
     * @param  int[]  $stockProductIds  stock quantities only
     */
    public function __construct(
        private array $productIds,
        private array $stockProductIds
    ) {
    }

    public function handle(): void
    {
        $settings = WooCommerceSetting::first();
        if (! $settings || ! $settings->store_url || ! $settings->consumer_key || ! $settings->consumer_secret) {
            return;
        }
        if (! $settings->auto_sync_products && ! $settings->instant_stock_push) {
            return;
        }

        $client = new Client((string) $settings->store_url, (string) $settings->consumer_key, (string) $settings->consumer_secret);
        $cache = Cache::store('file');
        $key = 'woo_auto_sync:'.md5(rtrim((string) $settings->store_url, '/').'|'.$settings->consumer_key);

        try {
            $this->addPending($cache, $key, $this->productIds, $this->stockProductIds);

            while (! $cache->has($key.':paused') && $this->hasPending($cache, $key)) {
                $lock = $cache->lock($key.':run', self::RUN_LOCK_SECONDS);
                if (! $lock->get()) {
                    return; // the running job picks our ids up
                }

                try {
                    [$products, $stock] = $this->takePending($cache, $key);
                    if (! $products && ! $stock) {
                        continue;
                    }

                    $reachable = StockWatcher::muted(fn () => $this->push($settings, $client, $products, $stock));
                    if (! $reachable) {
                        $this->addPending($cache, $key, $products, $stock);
                        $cache->put($key.':paused', true, self::PAUSE_SECONDS);
                        $this->log('stock.instant', 'warning', 'Pushes on change paused for '.(self::PAUSE_SECONDS / 60).' minutes: WooCommerce is not responding. Waiting changes will be sent after the pause.', [
                            'product_ids' => $products,
                            'stock_product_ids' => $stock,
                        ]);
                    }
                } finally {
                    $lock->release();
                }
            }
        } catch (LockTimeoutException $e) {
            $this->log('stock.instant', 'warning', 'Push on change skipped: the pending list was busy', [
                'product_ids' => $this->productIds,
                'stock_product_ids' => $this->stockProductIds,
            ]);
        }
    }

    /**
     * One round. Returns false when nothing went through and the store does
     * not answer at all — the caller then keeps the ids for later.
     */
    private function push(WooCommerceSetting $settings, Client $client, array $productIds, array $stockIds): bool
    {
        $productIds = $settings->auto_sync_products ? $productIds : [];
        $stockIds = $settings->instant_stock_push ? $stockIds : [];

        $sync = new SyncService($client);
        $succeeded = 0;
        $failed = 0;

        if ($productIds) {
            try {
                $result = $sync->pushProducts(false, null, null, null, null, [], $productIds);
                $succeeded += (int) ($result['created'] ?? 0) + (int) ($result['updated'] ?? 0);
                $failed += (int) ($result['errors'] ?? 0);
                $this->log(
                    'products.instant',
                    empty($result['errors']) ? 'info' : 'error',
                    'Product pushed on change: '.count($productIds).' product(s)',
                    ['product_ids' => $productIds] + $result
                );
            } catch (\Throwable $e) {
                $failed++;
                $this->log('products.instant', 'error', 'Product push on change failed: '.$e->getMessage(), ['product_ids' => $productIds]);
            }

            // Product push does not carry quantities; send them right after.
            $stockIds = array_values(array_unique(array_merge($stockIds, $productIds)));
        }

        // syncStock() logs targeted pushes itself ('stock.instant'). Never
        // call it with an empty list: that means "every product".
        if ($stockIds) {
            try {
                $result = $sync->syncStock(null, $stockIds);
                $succeeded += (int) ($result['updated'] ?? 0);
                $failed += (int) ($result['errors'] ?? 0);
            } catch (\Throwable $e) {
                $failed++;
                $this->log('stock.instant', 'error', 'Stock push on change failed: '.$e->getMessage(), ['product_ids' => $stockIds]);
            }
        }

        if ($succeeded > 0 || $failed === 0) {
            return true;
        }

        // Everything failed: the products themselves, or the store? A store
        // that answers this cheap request is up, so the errors belong to the
        // products (already logged) and retrying them would not help.
        try {
            return $client->getNoRetry('products', ['per_page' => 1, '_fields' => 'id'], 10, 5)->successful();
        } catch (\Throwable $e) {
            return false;
        }
    }

    // -------------------------------------------------------- pending list --

    private function addPending(Repository $cache, string $key, array $products, array $stock): void
    {
        $this->withPending($cache, $key, function (array $pending) use ($products, $stock) {
            foreach ($products as $id) {
                $pending['products'][(int) $id] = true;
            }
            foreach ($stock as $id) {
                $pending['stock'][(int) $id] = true;
            }

            return [$pending, null];
        });
    }

    /** Remove and return up to one round of ids: [productIds, stockIds]. */
    private function takePending(Repository $cache, string $key): array
    {
        return $this->withPending($cache, $key, function (array $pending) {
            $products = array_slice(array_keys($pending['products']), 0, self::ROUND);
            $stock = array_slice(array_keys($pending['stock']), 0, self::ROUND);
            foreach ($products as $id) {
                unset($pending['products'][$id]);
            }
            foreach ($stock as $id) {
                unset($pending['stock'][$id]);
            }

            return [$pending, [$products, $stock]];
        });
    }

    private function hasPending(Repository $cache, string $key): bool
    {
        $pending = $cache->get($key.':pending');

        return is_array($pending) && (! empty($pending['products']) || ! empty($pending['stock']));
    }

    /**
     * Read-modify-write of the pending list under a short mutex.
     * The callback returns [newPending, result].
     */
    private function withPending(Repository $cache, string $key, callable $callback)
    {
        return $cache->lock($key.':mutex', 10)->block(5, function () use ($cache, $key, $callback) {
            $pending = $cache->get($key.':pending');
            if (! is_array($pending)) {
                $pending = [];
            }
            $pending += ['products' => [], 'stock' => []];

            [$pending, $result] = $callback($pending);

            if ($pending['products'] || $pending['stock']) {
                $cache->put($key.':pending', $pending, self::PENDING_TTL);
            } else {
                $cache->forget($key.':pending');
            }

            return $result;
        });
    }

    private function log(string $action, string $level, string $message, array $context = []): void
    {
        try {
            WooCommerceLog::create([
                'action' => $action,
                'level' => $level,
                'message' => $message,
                'context' => $context,
            ]);
        } catch (\Throwable $e) {
        }
    }
}
