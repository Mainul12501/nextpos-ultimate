<?php

namespace App\Services\LandedCost;

use App\Models\PurchaseLandedCost;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Landed cost: a purchase's extra costs (freight, customs, insurance…) spread
 * over its lines and stored per line in purchase_details.landed_cost.
 *
 * Each cost is spread by its own method:
 *   value     line value = quantity × unit cost after line discount, tax excluded
 *   quantity  line quantity in BASE units (so cartons and pieces compare fairly)
 *   weight    base quantity × product weight
 * A basis that is all zero (e.g. no weights entered) falls back to value, then
 * quantity, then equal shares. The purchase's shipping can be included (by
 * value). Shares are rounded to 4 decimals and always add up to the exact
 * amount. All amounts are base currency, like the rest of the purchase.
 *
 * Landed costs never change the purchase's GrandTotal (they are often owed to
 * other parties); they only raise the cost of the goods.
 */
final class LandedCostService
{
    public static function migrated(): bool
    {
        static $migrated = null;

        try {
            return $migrated ??= Schema::hasTable('purchase_landed_costs') && Schema::hasColumn('purchase_details', 'landed_cost');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @param  array<int|string, array{value: float, qty_base: float, weight: float}>  $lines  weight = total weight of the line
     * @param  array<int, array{amount: float, method: string}>  $entries
     * @return array<int|string, float> share per line, same keys
     */
    public static function allocate(array $lines, array $entries, float $shipping = 0.0, bool $includeShipping = false): array
    {
        $shares = array_fill_keys(array_keys($lines), 0.0);
        if ($lines === []) {
            return $shares;
        }

        $pools = ['value' => 0.0, 'quantity' => 0.0, 'weight' => 0.0];
        foreach ($entries as $entry) {
            $method = in_array($entry['method'] ?? '', PurchaseLandedCost::METHODS, true) ? $entry['method'] : 'value';
            $pools[$method] += max(0.0, (float) ($entry['amount'] ?? 0));
        }
        if ($includeShipping) {
            $pools['value'] += max(0.0, $shipping);
        }

        $basis = [
            'value' => array_map(fn ($l) => max(0.0, (float) $l['value']), $lines),
            'quantity' => array_map(fn ($l) => max(0.0, (float) $l['qty_base']), $lines),
            'weight' => array_map(fn ($l) => max(0.0, (float) $l['weight']), $lines),
        ];
        $fallbacks = ['weight' => ['weight', 'value', 'quantity'], 'value' => ['value', 'quantity'], 'quantity' => ['quantity', 'value']];

        foreach ($pools as $method => $pool) {
            $pool = round($pool, 4);
            if ($pool <= 0) {
                continue;
            }

            $weights = null;
            foreach ($fallbacks[$method] as $candidate) {
                if (array_sum($basis[$candidate]) > 0) {
                    $weights = $basis[$candidate];
                    break;
                }
            }
            $weights ??= array_fill_keys(array_keys($lines), 1.0);
            $total = array_sum($weights);

            // Largest share last absorbs the rounding difference.
            $keys = array_keys(array_filter($weights, fn ($w) => $w > 0));
            usort($keys, fn ($a, $b) => $weights[$a] <=> $weights[$b]);
            $given = 0.0;
            foreach ($keys as $i => $key) {
                $share = $i === count($keys) - 1
                    ? round($pool - $given, 4)
                    : round($pool * $weights[$key] / $total, 4);
                $given += $share;
                $shares[$key] += $share;
            }
        }

        return array_map(fn ($s) => round($s, 4), $shares);
    }

    // ------------------------------------------------------------ purchases

    /** Save the purchase form's landed costs (when sent) and re-spread them. */
    public static function syncFromRequest(int $purchaseId, Request $request): void
    {
        if (! self::migrated()) {
            return;
        }

        if ($request->has('landed_costs')) {
            $raw = $request->input('landed_costs');
            $raw = is_string($raw) ? json_decode($raw, true) : $raw;
            $rows = [];
            foreach (is_array($raw) ? $raw : [] as $entry) {
                $amount = round((float) ($entry['amount'] ?? 0), 4);
                if (! is_array($entry) || $amount <= 0) {
                    continue;
                }
                $rows[] = [
                    'purchase_id' => $purchaseId,
                    'label' => mb_substr(trim((string) ($entry['label'] ?? '')) ?: __('Landed cost'), 0, 120),
                    'amount' => $amount,
                    'method' => in_array($entry['method'] ?? '', PurchaseLandedCost::METHODS, true) ? $entry['method'] : 'value',
                    'provider_id' => ! empty($entry['provider_id']) ? (int) $entry['provider_id'] : null,
                ];
            }

            PurchaseLandedCost::where('purchase_id', $purchaseId)->delete();
            foreach ($rows as $row) {
                PurchaseLandedCost::create($row);
            }
        }

        if ($request->has('landed_include_shipping')) {
            DB::table('purchases')->where('id', $purchaseId)
                ->update(['landed_include_shipping' => $request->boolean('landed_include_shipping')]);
        }

        self::reallocate($purchaseId);
        LandedCostJournal::sync($purchaseId);

        if ($request->boolean('landed_update_product_cost')) {
            self::updateProductCosts($purchaseId);
        }
    }

    /**
     * "Update the products' cost" (purchase form, received purchases only):
     * set each line's product / variant card cost to its landed unit cost —
     * unit cost after line discount + landed share, converted to the BASE
     * unit (the card cost is per base unit). Used by reports that value
     * stock at the card cost (profit by product, stock valuation).
     */
    public static function updateProductCosts(int $purchaseId): int
    {
        if (DB::table('purchases')->where('id', $purchaseId)->value('statut') !== 'received') {
            return 0;
        }

        $rows = DB::table('purchase_details as d')
            ->join('products as p', 'p.id', '=', 'd.product_id')
            ->leftJoin('units as u', 'u.id', '=', DB::raw('COALESCE(d.purchase_unit_id, p.unit_purchase_id, p.unit_id)'))
            ->where('d.purchase_id', $purchaseId)
            ->where('d.quantity', '>', 0)
            ->get(['d.product_id', 'd.product_variant_id', 'd.quantity', 'd.cost', 'd.discount', 'd.discount_method', 'd.TaxNet', 'd.tax_method', 'd.landed_cost', 'u.operator', 'u.operator_value']);

        $updated = 0;
        foreach ($rows as $r) {
            // Net unit cost as lib/lineCalc.js recomputeCostLine works it out:
            // after the line discount, and without the tax when the cost was
            // entered tax-inclusive (tax_method 2).
            $discountNet = (string) $r->discount_method === '2' ? (float) $r->discount : (float) $r->cost * (float) $r->discount / 100;
            $net = (float) $r->cost - $discountNet;
            if ((string) $r->tax_method === '2') {
                $net -= $net * (float) $r->TaxNet / 100;
            }
            $perPurchaseUnit = $net + (float) $r->landed_cost / (float) $r->quantity;
            $baseCost = round($perPurchaseUnit / self::factor($r), 4);
            if ($baseCost <= 0) {
                continue;
            }
            $r->product_variant_id
                ? DB::table('product_variants')->where('id', $r->product_variant_id)->update(['cost' => $baseCost])
                : DB::table('products')->where('id', $r->product_id)->update(['cost' => $baseCost]);
            $updated++;
        }

        return $updated;
    }

    /** Re-spread the stored landed costs over the purchase's current lines. */
    public static function reallocate(int $purchaseId): void
    {
        if (! self::migrated()) {
            return;
        }

        $purchase = DB::table('purchases')->where('id', $purchaseId)->first(['shipping', 'landed_include_shipping']);
        if (! $purchase) {
            return;
        }

        $entries = PurchaseLandedCost::where('purchase_id', $purchaseId)->get(['amount', 'method'])->toArray();
        $lines = self::linesFromDetails(DB::table('purchase_details as d')->where('d.purchase_id', $purchaseId));
        $shares = self::allocate($lines, $entries, (float) $purchase->shipping, (bool) $purchase->landed_include_shipping);

        $current = DB::table('purchase_details')->where('purchase_id', $purchaseId)->pluck('landed_cost', 'id');
        foreach ($shares as $detailId => $share) {
            if (abs((float) ($current[$detailId] ?? 0) - $share) > 0.00001) {
                DB::table('purchase_details')->where('id', $detailId)->update(['landed_cost' => $share]);
            }
        }
    }

    /**
     * Allocation preview for the purchase form: the same math on lines that
     * aren't saved yet. $details = [{product_id, quantity, Unit_cost,
     * discount, discount_Method, purchase_unit_id}], keyed as sent.
     */
    public static function preview(array $details, array $entries, float $shipping, bool $includeShipping): array
    {
        $productIds = array_filter(array_map(fn ($d) => (int) ($d['product_id'] ?? 0), $details));
        $products = DB::table('products')->whereIn('id', $productIds)->get(['id', 'weight', 'unit_id', 'unit_purchase_id'])->keyBy('id');
        $unitIds = array_filter(array_merge(
            array_map(fn ($d) => (int) ($d['purchase_unit_id'] ?? 0), $details),
            $products->pluck('unit_purchase_id')->all(),
            $products->pluck('unit_id')->all()
        ));
        $units = DB::table('units')->whereIn('id', $unitIds)->get(['id', 'operator', 'operator_value'])->keyBy('id');

        $lines = [];
        foreach ($details as $key => $d) {
            $p = $products->get((int) ($d['product_id'] ?? 0));
            $unit = $units->get((int) (($d['purchase_unit_id'] ?? null) ?: ($p->unit_purchase_id ?? null) ?: ($p->unit_id ?? 0)));
            $lines[$key] = self::line(
                (float) ($d['quantity'] ?? 0),
                (float) ($d['Unit_cost'] ?? 0),
                (float) ($d['discount'] ?? 0),
                (string) ($d['discount_Method'] ?? $d['discount_method'] ?? '2'),
                self::factor($unit),
                (float) ($p->weight ?? 0)
            );
        }

        return self::allocate($lines, $entries, $shipping, $includeShipping);
    }

    /** Stored landed costs for the purchase form / details. */
    public static function entriesFor(int $purchaseId): array
    {
        if (! self::migrated()) {
            return [];
        }

        return PurchaseLandedCost::where('purchase_id', $purchaseId)->orderBy('id')
            ->get(['id', 'label', 'amount', 'method', 'provider_id'])->toArray();
    }

    // ------------------------------------------------------------ internals

    private static function linesFromDetails($query): array
    {
        $rows = $query->join('products as p', 'p.id', '=', 'd.product_id')
            ->leftJoin('units as u', 'u.id', '=', DB::raw('COALESCE(d.purchase_unit_id, p.unit_purchase_id, p.unit_id)'))
            ->get(['d.id', 'd.quantity', 'd.cost', 'd.discount', 'd.discount_method', 'p.weight', 'u.operator', 'u.operator_value']);

        $lines = [];
        foreach ($rows as $r) {
            $lines[$r->id] = self::line((float) $r->quantity, (float) $r->cost, (float) $r->discount,
                (string) $r->discount_method, self::factor($r), (float) ($r->weight ?? 0));
        }

        return $lines;
    }

    /** Same discount rule as lib/lineCalc.js: method '2' = fixed per unit, else percent. */
    private static function line(float $qty, float $cost, float $discount, string $discountMethod, float $factor, float $weight): array
    {
        $net = $discountMethod === '2' ? $cost - $discount : $cost - $cost * $discount / 100;
        $qtyBase = $qty * $factor;

        return [
            'value' => max(0.0, $qty * $net),
            'qty_base' => max(0.0, $qtyBase),
            'weight' => max(0.0, $qtyBase * $weight),
        ];
    }

    /** Base units per one unit of the given `units` row. */
    private static function factor($unit): float
    {
        if (! $unit) {
            return 1.0;
        }
        $value = (float) ($unit->operator_value ?? 1) ?: 1.0;

        return ($unit->operator ?? '*') === '/' ? 1 / $value : $value;
    }
}
