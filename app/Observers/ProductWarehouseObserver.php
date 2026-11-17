<?php

namespace App\Observers;

use App\Models\product_warehouse;
use App\Services\Shopify\StockWatcher as ShopifyStockWatcher;
use App\Services\WooCommerce\StockWatcher as WooStockWatcher;

/**
 * Every stock movement in the ERP lands on a product_warehouse row, whoever
 * caused it — a POS sale, a return, an adjustment, a transfer, a received
 * purchase. Watching the row rather than each of those controllers means a
 * flow nobody remembered to hook still reaches the connected shops.
 *
 * The limit of that: bulk `->update()` calls on the query builder fire no
 * model events, so a mass operation (deleting a product, resetting a
 * warehouse) is not seen here. Those are exactly the cases the scheduled
 * inventory push exists to reconcile.
 */
class ProductWarehouseObserver
{
    public function saved(product_warehouse $row): void
    {
        // A row saved for some other reason — manage_stock toggled, a
        // timestamp touched — is not a stock movement and is not worth an API
        // call to Shopify.
        if (! $row->wasRecentlyCreated && ! $row->wasChanged('qte')) {
            return;
        }

        // Each integration decides for itself: both are off by default and
        // cost one cached lookup to say no. They are told separately rather
        // than through a shared watcher because they disagree about what a
        // stock change means — Shopify publishes one warehouse to one
        // location, Woo publishes the total — and because muting one during
        // its own sync must not silence the other.
        ShopifyStockWatcher::record($row);
        WooStockWatcher::record($row);
    }
}
