<?php

namespace App\Services\LandedCost;

use App\Listeners\AccountingV2\Concerns\SkipsWhenManual;
use App\Models\Purchase;
use App\Models\PurchaseLandedCost;
use App\Services\AccountingV2\AccountingHelper;

/**
 * Accounting for landed costs, when automatic journals are on (Settings →
 * Features, same switch and baseline date as the purchase journals):
 * Inventory Dr / Accrued Landed Costs Cr for the sum of the purchase's landed
 * costs. Kept in step on every save; a deleted purchase is reversed.
 * Shipping spread as landed cost is NOT posted again — it is already part of
 * the purchase's GrandTotal entry.
 */
final class LandedCostJournal
{
    use SkipsWhenManual;

    public static function sync(int $purchaseId): void
    {
        if (! LandedCostService::migrated()) {
            return;
        }

        $purchase = Purchase::find($purchaseId);
        if (! $purchase) {
            return;
        }

        $journal = new self;
        if ($journal->shouldSkipForModel($purchase)) {
            return;
        }

        $target = $purchase->deleted_at
            ? 0.0
            : (float) PurchaseLandedCost::where('purchase_id', $purchaseId)->sum('amount');

        AccountingHelper::fromPurchaseLandedCost($purchase, round($target, 4));
    }
}
