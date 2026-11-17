<?php

namespace App\Services\Reorder;

use App\Models\ProductReorderSetting;
use Illuminate\Support\Facades\DB;

/**
 * Smart reordering: what to buy, per product / variant / warehouse.
 *
 * All quantities are worked out in the product's BASE unit (product_warehouse
 * stock is stored in it), then the suggestion is converted to the PURCHASE
 * unit and rounded up to the product's order multiple / minimum.
 *
 *   daily sales    = (completed sales − received returns) over the last
 *                    sales_window_days ÷ sales_window_days
 *   reorder point  = daily × (lead time + safety days), never below the
 *                    product's stock alert
 *   order-up-to    = daily × (lead + safety + coverage days); with no recent
 *                    sales, twice the stock alert
 *   available      = on hand + on open purchase orders (ordered / pending)
 *   suggestion     = order-up-to − available, when available ≤ reorder point
 *
 * Supplier: the product's preferred supplier, else the one it was last bought
 * from. Products with an active MRP bill of materials are flagged
 * "manufactured" (make them, don't buy them). Services and combos are skipped.
 */
final class ReorderEngine
{
    /** Base units per one unit of the aliased `units` row. */
    private static function factorSql(string $alias): string
    {
        return "CASE WHEN {$alias}.operator = '/' THEN 1 / COALESCE(NULLIF({$alias}.operator_value, 0), 1) ELSE COALESCE(NULLIF({$alias}.operator_value, 0), 1) END";
    }

    /**
     * @param  int[]  $warehouseIds  warehouses the user may see
     * @param  array{warehouse_id?: int|null, provider_id?: int|null, category_id?: int|null, search?: string|null, only_needed?: bool}  $filters
     * @return array{rows: array<int, array>, summary: array, defaults: array}
     */
    public static function suggestions(array $warehouseIds, array $filters = []): array
    {
        $defaults = ReorderSettings::defaults();
        $empty = ['rows' => [], 'summary' => self::summary([]), 'defaults' => $defaults];

        if (! empty($filters['warehouse_id'])) {
            $warehouseIds = array_values(array_intersect($warehouseIds, [(int) $filters['warehouse_id']]));
        }
        if ($warehouseIds === []) {
            return $empty;
        }

        $stock = self::stockRows($warehouseIds, $filters);
        if ($stock->isEmpty()) {
            return $empty;
        }

        $productIds = $stock->pluck('product_id')->unique()->values()->all();
        $since = now()->subDays($defaults['sales_window_days'])->toDateString();

        $sold = self::soldQty($productIds, $warehouseIds, $since);
        $returned = self::returnedQty($productIds, $warehouseIds, $since);
        $incoming = self::incomingQty($productIds, $warehouseIds);
        $last = self::lastPurchases($productIds);
        $manufactured = self::manufacturedProductIds($productIds);
        $overrides = ReorderSettings::migrated()
            ? ProductReorderSetting::whereIn('product_id', $productIds)->get()->keyBy('product_id')
            : collect();

        $rows = [];
        foreach ($stock as $s) {
            $o = $overrides->get($s->product_id);
            if ($o && $o->excluded) {
                continue;
            }

            $key = $s->product_id.':'.($s->product_variant_id ?? 0).':'.$s->warehouse_id;
            $soldQty = max(0.0, (float) ($sold[$key] ?? 0) - (float) ($returned[$key] ?? 0));
            $daily = $soldQty / $defaults['sales_window_days'];

            $lead = $o?->lead_time_days ?? $defaults['lead_time_days'];
            $safety = $o?->safety_days ?? $defaults['safety_days'];
            $coverage = $defaults['coverage_days'];
            $alert = max(0.0, (float) $s->stock_alert);

            $onHand = (float) $s->qte;
            $onOrder = (float) ($incoming[$key] ?? 0);
            $available = $onHand + $onOrder;

            $reorderPoint = max($daily * ($lead + $safety), $alert);
            $target = $daily > 0
                ? max($daily * ($lead + $safety + $coverage), $reorderPoint)
                : $alert * 2;

            $needed = $target > 0 && $available <= $reorderPoint;
            $purchaseFactor = max(1e-9, (float) $s->purchase_factor);
            $purchaseQty = $needed ? self::roundOrder(($target - $available) / $purchaseFactor, $o) : 0.0;

            $daysLeft = $daily > 0 ? round($onHand / $daily, 1) : null;
            $status = $onHand <= 0 ? 'out'
                : (! $needed ? 'ok'
                    : ($daysLeft !== null && $daysLeft < $lead ? 'critical' : 'low'));

            $lastLine = $last[$s->product_id.':'.($s->product_variant_id ?? 0)] ?? $last[$s->product_id.':0'] ?? null;
            $providerId = $o?->preferred_provider_id ?? ($lastLine->provider_id ?? null);

            if (! empty($filters['provider_id']) && (int) $providerId !== (int) $filters['provider_id']) {
                continue;
            }
            if (($filters['only_needed'] ?? true) && ! $needed) {
                continue;
            }

            $rows[] = [
                'key' => $key,
                'product_id' => (int) $s->product_id,
                'product_variant_id' => $s->product_variant_id !== null ? (int) $s->product_variant_id : null,
                'warehouse_id' => (int) $s->warehouse_id,
                'warehouse' => $s->warehouse_name,
                'code' => $s->variant_code ?: $s->code,
                'name' => $s->variant_name ? '['.$s->variant_name.'] '.$s->name : $s->name,
                'category_id' => $s->category_id !== null ? (int) $s->category_id : null,
                'base_unit' => $s->base_unit,
                'purchase_unit' => $s->purchase_unit ?: $s->base_unit,
                'purchase_unit_id' => $s->purchase_unit_id !== null ? (int) $s->purchase_unit_id : null,
                'purchase_factor' => round($purchaseFactor, 6),
                'on_hand' => round($onHand, 4),
                'on_order' => round($onOrder, 4),
                'sold' => round($soldQty, 4),
                'daily_sales' => round($daily, 4),
                'days_left' => $daysLeft,
                'stock_alert' => $alert,
                'lead_time_days' => (int) $lead,
                'safety_days' => (int) $safety,
                'reorder_point' => round($reorderPoint, 2),
                'order_up_to' => round($target, 2),
                'suggested_qty' => $purchaseQty,
                'provider_id' => $providerId !== null ? (int) $providerId : null,
                // Last purchase line cost is per purchase unit; the product
                // card cost is per base unit (as the profit report reads it).
                'unit_cost' => $lastLine
                    ? (float) $lastLine->cost
                    : round((float) ($s->variant_cost ?? $s->cost ?? 0) * $purchaseFactor, 4),
                'manufactured' => isset($manufactured[$s->product_id]),
                'status' => $status,
            ];
        }

        $providerNames = DB::table('providers')
            ->whereIn('id', array_filter(array_unique(array_column($rows, 'provider_id'))))
            ->pluck('name', 'id');
        foreach ($rows as &$row) {
            $row['provider'] = $row['provider_id'] ? ($providerNames[$row['provider_id']] ?? null) : null;
            $row['line_total'] = round($row['suggested_qty'] * $row['unit_cost'], 4);
        }
        unset($row);

        // Most urgent first: out of stock, critical, low; then fewest days left.
        $rank = ['out' => 0, 'critical' => 1, 'low' => 2, 'ok' => 3];
        usort($rows, fn ($a, $b) => [$rank[$a['status']], $a['days_left'] ?? INF, $a['name']]
            <=> [$rank[$b['status']], $b['days_left'] ?? INF, $b['name']]);

        return ['rows' => $rows, 'summary' => self::summary($rows), 'defaults' => $defaults];
    }

    /** Order quantity in purchase units: rounded up to the multiple, at least the minimum. */
    private static function roundOrder(float $qty, ?ProductReorderSetting $o): float
    {
        if ($qty <= 0) {
            return 0.0;
        }

        $qty = ceil($qty - 1e-9);
        $multiple = (float) ($o?->order_multiple ?? 0);
        if ($multiple > 0) {
            $qty = ceil($qty / $multiple - 1e-9) * $multiple;
        }

        return round(max($qty, (float) ($o?->min_order_qty ?? 0)), 4);
    }

    private static function summary(array $rows): array
    {
        $count = fn ($status) => count(array_filter($rows, fn ($r) => $r['status'] === $status));

        return [
            'total' => count($rows),
            'out' => $count('out'),
            'critical' => $count('critical'),
            'low' => $count('low'),
            'manufactured' => count(array_filter($rows, fn ($r) => $r['manufactured'])),
            'value' => round(array_sum(array_column($rows, 'line_total')), 2),
        ];
    }

    private static function stockRows(array $warehouseIds, array $filters)
    {
        $search = trim((string) ($filters['search'] ?? ''));

        return DB::table('product_warehouse as pw')
            ->join('products as p', 'p.id', '=', 'pw.product_id')
            ->join('warehouses as w', 'w.id', '=', 'pw.warehouse_id')
            ->leftJoin('product_variants as pv', 'pv.id', '=', 'pw.product_variant_id')
            ->leftJoin('units as bu', 'bu.id', '=', 'p.unit_id')
            ->leftJoin('units as pu', 'pu.id', '=', DB::raw('COALESCE(p.unit_purchase_id, p.unit_id)'))
            ->whereNull('pw.deleted_at')
            ->whereNull('p.deleted_at')
            ->whereNull('w.deleted_at')
            ->where('pw.manage_stock', 1)
            ->where('p.is_active', 1)
            ->whereIn('p.type', ['is_single', 'is_variant'])
            ->whereIn('pw.warehouse_id', $warehouseIds)
            // A variant product's stock lives on its variant rows.
            ->where(fn ($q) => $q->where('p.type', 'is_single')
                ->orWhere(fn ($v) => $v->whereNotNull('pw.product_variant_id')->whereNull('pv.deleted_at')))
            ->when(! empty($filters['category_id']), fn ($q) => $q->where('p.category_id', (int) $filters['category_id']))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('p.name', 'like', "%{$search}%")
                ->orWhere('p.code', 'like', "%{$search}%")
                ->orWhere('pv.name', 'like', "%{$search}%")
                ->orWhere('pv.code', 'like', "%{$search}%")))
            ->get([
                'pw.product_id', 'pw.product_variant_id', 'pw.warehouse_id', 'pw.qte',
                'p.name', 'p.code', 'p.cost', 'p.stock_alert', 'p.category_id',
                'pv.name as variant_name', 'pv.code as variant_code', 'pv.cost as variant_cost',
                'w.name as warehouse_name', 'bu.ShortName as base_unit', 'pu.ShortName as purchase_unit',
                'pu.id as purchase_unit_id', DB::raw(self::factorSql('pu').' as purchase_factor'),
            ]);
    }

    /** Completed sales since $since, in base units, keyed product:variant:warehouse. */
    private static function soldQty(array $productIds, array $warehouseIds, string $since): array
    {
        return DB::table('sale_details as d')
            ->join('sales as s', 's.id', '=', 'd.sale_id')
            ->join('products as p', 'p.id', '=', 'd.product_id')
            ->leftJoin('units as u', 'u.id', '=', DB::raw('COALESCE(d.sale_unit_id, p.unit_sale_id, p.unit_id)'))
            ->whereNull('s.deleted_at')
            ->where('s.statut', 'completed')
            ->where('s.date', '>=', $since)
            ->whereIn('d.product_id', $productIds)
            ->whereIn('s.warehouse_id', $warehouseIds)
            ->groupBy('d.product_id', 'd.product_variant_id', 's.warehouse_id')
            ->selectRaw("CONCAT(d.product_id, ':', COALESCE(d.product_variant_id, 0), ':', s.warehouse_id) as k,
                SUM(d.quantity * COALESCE(d.pack_multiplier, 1) * (".self::factorSql('u').')) as qty')
            ->pluck('qty', 'k')
            ->all();
    }

    /** Received sale returns since $since, in base units (they cancel sales). */
    private static function returnedQty(array $productIds, array $warehouseIds, string $since): array
    {
        return DB::table('sale_return_details as d')
            ->join('sale_returns as r', 'r.id', '=', 'd.sale_return_id')
            ->join('products as p', 'p.id', '=', 'd.product_id')
            ->leftJoin('units as u', 'u.id', '=', DB::raw('COALESCE(d.sale_unit_id, p.unit_sale_id, p.unit_id)'))
            ->whereNull('r.deleted_at')
            ->where('r.statut', 'received')
            ->where('r.date', '>=', $since)
            ->whereIn('d.product_id', $productIds)
            ->whereIn('r.warehouse_id', $warehouseIds)
            ->groupBy('d.product_id', 'd.product_variant_id', 'r.warehouse_id')
            ->selectRaw("CONCAT(d.product_id, ':', COALESCE(d.product_variant_id, 0), ':', r.warehouse_id) as k,
                SUM(d.quantity * COALESCE(d.pack_multiplier, 1) * (".self::factorSql('u').')) as qty')
            ->pluck('qty', 'k')
            ->all();
    }

    /** Quantities on purchase orders not received yet (ordered / pending), in base units. */
    private static function incomingQty(array $productIds, array $warehouseIds): array
    {
        return DB::table('purchase_details as d')
            ->join('purchases as pu', 'pu.id', '=', 'd.purchase_id')
            ->join('products as p', 'p.id', '=', 'd.product_id')
            ->leftJoin('units as u', 'u.id', '=', DB::raw('COALESCE(d.purchase_unit_id, p.unit_purchase_id, p.unit_id)'))
            ->whereNull('pu.deleted_at')
            ->whereIn('pu.statut', ['ordered', 'pending'])
            ->whereIn('d.product_id', $productIds)
            ->whereIn('pu.warehouse_id', $warehouseIds)
            ->groupBy('d.product_id', 'd.product_variant_id', 'pu.warehouse_id')
            ->selectRaw("CONCAT(d.product_id, ':', COALESCE(d.product_variant_id, 0), ':', pu.warehouse_id) as k,
                SUM(d.quantity * (".self::factorSql('u').')) as qty')
            ->pluck('qty', 'k')
            ->all();
    }

    /**
     * The most recent received purchase line per product:variant (and per
     * product:0 as a fallback for variants never bought on their own):
     * supplier + unit cost in the purchase unit.
     */
    private static function lastPurchases(array $productIds): array
    {
        $latestIds = DB::table('purchase_details as d')
            ->join('purchases as pu', 'pu.id', '=', 'd.purchase_id')
            ->whereNull('pu.deleted_at')
            ->where('pu.statut', 'received')
            ->whereIn('d.product_id', $productIds)
            ->groupBy('d.product_id', 'd.product_variant_id')
            ->selectRaw('MAX(d.id) as id')
            ->pluck('id');

        $out = [];
        if ($latestIds->isEmpty()) {
            return $out;
        }

        $lines = DB::table('purchase_details as d')
            ->join('purchases as pu', 'pu.id', '=', 'd.purchase_id')
            ->whereIn('d.id', $latestIds)
            ->orderBy('d.id')
            ->get(['d.product_id', 'd.product_variant_id', 'd.cost', 'pu.provider_id']);

        foreach ($lines as $line) {
            $out[$line->product_id.':'.($line->product_variant_id ?? 0)] = $line;
            $out[$line->product_id.':0'] ??= $line;
        }

        return $out;
    }

    /** @return array<int, true> products with an active bill of materials */
    private static function manufacturedProductIds(array $productIds): array
    {
        try {
            return DB::table('mrp_boms')
                ->whereNull('deleted_at')
                ->where('status', 'active')
                ->whereIn('product_id', $productIds)
                ->pluck('product_id')
                ->mapWithKeys(fn ($id) => [(int) $id => true])
                ->all();
        } catch (\Throwable $e) {
            return []; // MRP not installed
        }
    }
}
