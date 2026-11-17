<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use App\Models\PurchaseLandedCost;
use App\Services\LandedCost\LandedCostService;
use Illuminate\Http\Request;

/**
 * Purchase form → Landed costs: live allocation preview, computed by the same
 * LandedCostService math the save uses, so the form shows exactly what will
 * be stored (including the by-weight split, which needs product weights).
 */
class LandedCostController extends BaseController
{
    /**
     * POST purchases/landed-cost/preview {details: [{product_id, quantity,
     * Unit_cost, discount, discount_Method, purchase_unit_id}], landed_costs:
     * [{amount, method}], shipping, include_shipping} → {shares: [..]} per detail index.
     */
    public function preview(Request $request)
    {
        $user = $request->user('api');
        if (! $user->can('create', Purchase::class) && ! $user->can('update', Purchase::class)) {
            abort(403);
        }

        $data = $request->validate([
            'details' => 'array|max:1000',
            'details.*.product_id' => 'required|integer',
            'details.*.quantity' => 'nullable|numeric',
            'details.*.Unit_cost' => 'nullable|numeric',
            // validate() returns only listed keys: these drive units and value.
            'details.*.purchase_unit_id' => 'nullable|integer',
            'details.*.discount' => 'nullable|numeric',
            'details.*.discount_Method' => 'nullable|in:1,2',
            'landed_costs' => 'array|max:50',
            'landed_costs.*.amount' => 'nullable|numeric',
            'landed_costs.*.method' => 'nullable|in:'.implode(',', PurchaseLandedCost::METHODS),
            'shipping' => 'nullable|numeric',
            'include_shipping' => 'nullable|boolean',
        ]);

        $details = array_values($data['details'] ?? []);
        $shares = LandedCostService::preview(
            $details,
            array_values($data['landed_costs'] ?? []),
            (float) ($data['shipping'] ?? 0),
            (bool) ($data['include_shipping'] ?? false)
        );

        return response()->json(['shares' => array_values($shares)]);
    }

    /**
     * GET report/landed-costs {from, to, provider_id?, warehouse_id?}
     * Purchases with landed costs in the period (base currency): goods value,
     * landed total and its share, plus totals by type (label), by who was paid
     * and by spreading method. Same permission as the purchases report.
     */
    public function report(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'ReportPurchases', Purchase::class);

        $from = $request->filled('from') ? \Carbon\Carbon::parse($request->from)->toDateString() : now()->startOfMonth()->toDateString();
        $to = $request->filled('to') ? \Carbon\Carbon::parse($request->to)->toDateString() : now()->toDateString();
        $warehouseIds = $this->userWarehouseIds();
        if ($warehouseFilter = $this->filterWarehouseId($request->input('warehouse_id'))) {
            $warehouseIds = [$warehouseFilter];
        }
        $providerId = $request->input('provider_id') ? (int) $request->input('provider_id') : null;

        $empty = ['rows' => [], 'summary' => ['purchases' => 0, 'goods_value' => 0, 'landed_total' => 0, 'pct' => 0],
            'by_label' => [], 'by_paid_to' => [], 'by_method' => []];
        if (! LandedCostService::migrated() || $warehouseIds === []) {
            return response()->json($empty + $this->reportFilters());
        }

        $purchases = \DB::table('purchases as p')
            ->leftJoin('providers as s', 's.id', '=', 'p.provider_id')
            ->leftJoin('warehouses as w', 'w.id', '=', 'p.warehouse_id')
            ->whereNull('p.deleted_at')
            ->whereBetween('p.date', [$from, $to])
            ->whereIn('p.warehouse_id', $warehouseIds)
            ->when($providerId, fn ($q) => $q->where('p.provider_id', $providerId))
            ->where(fn ($q) => $q->whereExists(fn ($e) => $e->from('purchase_landed_costs as c')->whereColumn('c.purchase_id', 'p.id'))
                ->orWhere(fn ($x) => $x->where('p.landed_include_shipping', 1)->where('p.shipping', '>', 0)))
            ->orderByDesc('p.date')->orderByDesc('p.id')
            ->get(['p.id', 'p.Ref', 'p.date', 'p.statut', 'p.shipping', 'p.landed_include_shipping', 's.name as supplier', 'w.name as warehouse']);

        if ($purchases->isEmpty()) {
            return response()->json($empty + $this->reportFilters());
        }

        $ids = $purchases->pluck('id')->all();
        // Goods value = quantity × unit cost after line discount, tax excluded (the "value" basis).
        $goods = \DB::table('purchase_details')->whereIn('purchase_id', $ids)
            ->groupBy('purchase_id')
            ->selectRaw("purchase_id, SUM(quantity * CASE WHEN discount_method = '2' THEN cost - discount ELSE cost * (1 - discount / 100) END) as value")
            ->pluck('value', 'purchase_id');
        $entries = \DB::table('purchase_landed_costs as c')
            ->leftJoin('providers as s', 's.id', '=', 'c.provider_id')
            ->whereIn('c.purchase_id', $ids)
            ->get(['c.purchase_id', 'c.label', 'c.amount', 'c.method', 's.name as paid_to']);
        $byPurchase = $entries->groupBy('purchase_id');

        $rows = [];
        $byLabel = [];
        $byPaidTo = [];
        $byMethod = [];
        foreach ($purchases as $p) {
            $shipping = $p->landed_include_shipping ? (float) $p->shipping : 0.0;
            $landed = (float) $byPurchase->get($p->id, collect())->sum('amount') + $shipping;
            $value = (float) ($goods[$p->id] ?? 0);
            $rows[] = [
                'id' => $p->id, 'Ref' => $p->Ref, 'date' => $p->date, 'statut' => $p->statut,
                'supplier' => $p->supplier, 'warehouse' => $p->warehouse,
                'goods_value' => round($value, 2), 'landed_total' => round($landed, 2),
                'pct' => $value > 0 ? round($landed / $value * 100, 2) : null,
            ];
            if ($shipping > 0) {
                $byLabel[__('Shipping')] = ($byLabel[__('Shipping')] ?? 0) + $shipping;
                $byPaidTo[$p->supplier ?: '—'] = ($byPaidTo[$p->supplier ?: '—'] ?? 0) + $shipping;
                $byMethod['value'] = ($byMethod['value'] ?? 0) + $shipping;
            }
        }
        foreach ($entries as $e) {
            $byLabel[$e->label] = ($byLabel[$e->label] ?? 0) + (float) $e->amount;
            $byPaidTo[$e->paid_to ?: '—'] = ($byPaidTo[$e->paid_to ?: '—'] ?? 0) + (float) $e->amount;
            $byMethod[$e->method] = ($byMethod[$e->method] ?? 0) + (float) $e->amount;
        }

        $list = function (array $map) {
            arsort($map);

            return collect($map)->map(fn ($amount, $name) => ['name' => (string) $name, 'amount' => round($amount, 2)])->values();
        };
        $goodsTotal = array_sum(array_column($rows, 'goods_value'));
        $landedTotal = array_sum(array_column($rows, 'landed_total'));

        return response()->json([
            'rows' => $rows,
            'summary' => [
                'purchases' => count($rows),
                'goods_value' => round($goodsTotal, 2),
                'landed_total' => round($landedTotal, 2),
                'pct' => $goodsTotal > 0 ? round($landedTotal / $goodsTotal * 100, 2) : 0,
            ],
            'by_label' => $list($byLabel),
            'by_paid_to' => $list($byPaidTo),
            'by_method' => $list($byMethod),
        ] + $this->reportFilters());
    }

    private function reportFilters(): array
    {
        return [
            'warehouses' => \DB::table('warehouses')->whereNull('deleted_at')->whereIn('id', $this->userWarehouseIds())->orderBy('name')->get(['id', 'name']),
            'providers' => \DB::table('providers')->whereNull('deleted_at')->orderBy('name')->get(['id', 'name']),
        ];
    }
}
