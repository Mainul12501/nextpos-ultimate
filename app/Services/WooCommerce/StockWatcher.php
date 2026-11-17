<?php

namespace App\Services\WooCommerce;

use App\Jobs\WooCommerceAutoSyncJob;
use App\Models\Product;
use App\Models\product_warehouse;
use App\Models\WooCommerceSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

/**
 * Noticing that stock moved — or that a product was created or edited — and
 * telling WooCommerce at once.
 *
 * Two opt-in switches on the WooCommerce settings:
 *
 *  - instant_stock_push: any stock movement (POS sale, return, purchase,
 *    adjustment, transfer) pushes the new quantity of the products involved.
 *  - auto_sync_products: a product created, edited or duplicated in the POS
 *    is pushed (details, variants, images), followed by its stock.
 *
 * The same idea as App\Services\Shopify\StockWatcher, and deliberately a
 * separate class rather than a shared one: the two integrations disagree about
 * what a stock change means. Shopify writes one warehouse's figure to one
 * location; Woo publishes the total across every warehouse. Muting also has to
 * be per-integration — while a Woo pull is writing local stock, a Shopify shop
 * still wants to hear about it.
 *
 * As with Shopify: nothing is pushed inside the request, changes are collected
 * and sent as one job, and the job carries ids rather than quantities so it
 * always sends what the ERP holds when it runs.
 */
class StockWatcher
{
    /** Suppressed while a Woo sync is itself writing local stock. */
    private static bool $muted = false;

    /** product_id => true — stock moved */
    private static array $pending = [];

    /** product_id => true — product created or edited */
    private static array $pendingProducts = [];

    private static bool $flushRegistered = false;

    /** Settings row when a switch is on, null when off; false until looked up. */
    private static $settings = false;

    /**
     * Run something without the Woo watcher reacting to it.
     *
     * An order pulled from Woo reduces ERP stock, and Woo has already reduced
     * its own — pushing the same number back is a wasted call. Scoped to this
     * integration on purpose: the change still reaches Shopify, which has not
     * heard about that sale.
     */
    public static function muted(callable $callback)
    {
        $was = self::$muted;
        self::$muted = true;

        try {
            return $callback();
        } finally {
            self::$muted = $was;
        }
    }

    public static function isMuted(): bool
    {
        return self::$muted;
    }

    /**
     * Mute for the rest of the calling scope: `$mute = StockWatcher::scope();`
     * at the top of a method, and it lifts when that method returns — however
     * it returns. Used by the long pull methods, where wrapping the whole body
     * in a closure would be a worse change than one line at the top.
     */
    public static function scope(): object
    {
        $was = self::$muted;
        self::$muted = true;

        return new class($was)
        {
            public function __construct(private bool $was) {}

            public function __destruct()
            {
                StockWatcher::restore($this->was);
            }
        };
    }

    /** @internal for the scope guard above. */
    public static function restore(bool $was): void
    {
        self::$muted = $was;
    }

    /** Called by the product_warehouse observer when a quantity changes. */
    public static function record(product_warehouse $row): void
    {
        if (self::$muted) {
            return;
        }

        $productId = (int) $row->product_id;
        if (! $productId || ! self::enabled('instant_stock_push')) {
            return;
        }

        // Variants are pushed through their parent product — Woo addresses a
        // variation by the product it belongs to — so the variant id is not
        // needed here, only the product it hangs off.
        self::$pending[$productId] = true;
        self::registerFlush();
    }

    /** A product was created, edited or duplicated in the POS. */
    public static function productSaved($productId): void
    {
        $productId = (int) $productId;
        if (self::$muted || $productId <= 0 || ! self::enabled('auto_sync_products')) {
            return;
        }

        self::$pendingProducts[$productId] = true;
        self::registerFlush();
    }

    /** Send what has piled up. Called at the end of the request. */
    public static function flush(): void
    {
        $stock = array_keys(self::$pending);
        $products = array_keys(self::$pendingProducts);
        self::$pending = [];
        self::$pendingProducts = [];

        if (! $stock && ! $products) {
            return;
        }

        if ($stock) {
            // A combo's stock is derived from its components, so a component
            // that moved changes the combo Woo is showing too.
            $stock = array_values(array_unique(array_merge($stock, self::combosContaining($stock))));

            // A pushed product has its stock sent with it; a product never
            // linked to Woo has nowhere to send stock to.
            $stock = self::linkedToWoo(array_values(array_diff($stock, $products)));
        }

        // One job; it works through the ids in rounds itself.
        if ($stock || $products) {
            self::send(WooCommerceAutoSyncJob::dispatch($products, $stock));
        }
    }

    /**
     * How the push actually leaves.
     *
     * In a web request — a POS sale, an adjustment saved in the admin — it
     * runs **after the response has been sent**, in the same process: no queue
     * worker to install, and the cashier never waits for WooCommerce, because
     * the sale has already been answered before the call is made.
     *
     * On the CLI, and inside a queued job, there is no response to come after,
     * so it is queued. On 'webhooks' rather than the module's own
     * 'woocommerce' queue, which carries the hours-long catalogue batches an
     * instant push must not wait behind.
     */
    private static function send($pending): void
    {
        app()->runningInConsole()
            ? $pending->onQueue('webhooks')
            : $pending->afterResponse();
    }

    /** Forget everything — for tests, and between jobs in a worker. */
    public static function reset(): void
    {
        self::$pending = [];
        self::$pendingProducts = [];
        self::$settings = false;
    }

    // ------------------------------------------------------------ internal --

    private static function enabled(string $flag): bool
    {
        if (self::$settings === false) {
            self::$settings = self::lookupSettings();
        }

        return self::$settings !== null && (bool) self::$settings->{$flag};
    }

    /**
     * Read once per request (and again for each queued job). Guarded because
     * this runs on every stock write in the application, including during an
     * install or an update where the table or a column may not exist yet — a
     * stock movement must never fail because of an integration that is not
     * there. A missing column reads as null, i.e. switched off.
     */
    private static function lookupSettings(): ?WooCommerceSetting
    {
        try {
            $settings = WooCommerceSetting::first();
            if (! $settings
                || ! $settings->store_url || ! $settings->consumer_key || ! $settings->consumer_secret
                || (! $settings->instant_stock_push && ! $settings->auto_sync_products)) {
                return null;
            }

            return $settings;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Combos built from any of these products. */
    private static function combosContaining(array $productIds): array
    {
        try {
            return DB::table('combined_products')
                ->whereIn('combined_product_id', $productIds)
                ->pluck('product_id')
                ->map(fn ($id) => (int) $id)
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Of these products, the ones linked to a Woo product. */
    private static function linkedToWoo(array $productIds): array
    {
        if (! $productIds) {
            return [];
        }

        try {
            return Product::whereIn('id', $productIds)
                ->whereNotNull('woocommerce_id')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    private static function registerFlush(): void
    {
        if (self::$flushRegistered) {
            return;
        }

        self::$flushRegistered = true;

        app()->terminating(function () {
            self::flush();
        });

        // A worker process does not terminate between jobs, so anything that
        // moves stock inside a job would otherwise sit in the buffer until the
        // worker exited. The settings are re-read for the next job, so a
        // switch turned off in the admin is honoured by a long-lived worker.
        Queue::after(function () {
            self::flush();
            self::$settings = false;
        });
    }
}
