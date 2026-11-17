<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use App\Services\Reorder\ReorderEngine;
use App\Services\Reorder\ReorderSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Smart reordering: purchase suggestions (ReorderEngine) and the global
 * defaults behind them. Anyone who may create purchases may use it; rows
 * are limited to the warehouses the user can access.
 */
class ReorderController extends BaseController
{
    /**
     * GET reorder/suggestions {warehouse_id?, provider_id?, category_id?, search?, all?}
     * all=1 also lists products that don't need ordering yet.
     */
    public function suggestions(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'create', Purchase::class);

        $result = ReorderEngine::suggestions($this->userWarehouseIds(), [
            'warehouse_id' => $this->filterWarehouseId($request->input('warehouse_id')),
            'provider_id' => $request->input('provider_id') ? (int) $request->input('provider_id') : null,
            'category_id' => $request->input('category_id') ? (int) $request->input('category_id') : null,
            'search' => $request->input('search'),
            'only_needed' => ! $request->boolean('all'),
        ]);

        return response()->json($result + [
            'migrated' => ReorderSettings::migrated(),
            'warehouses' => DB::table('warehouses')->whereNull('deleted_at')
                ->whereIn('id', $this->userWarehouseIds())->orderBy('name')->get(['id', 'name']),
            'providers' => DB::table('providers')->whereNull('deleted_at')->orderBy('name')->get(['id', 'name']),
            'categories' => DB::table('categories')->whereNull('deleted_at')->orderBy('name')->get(['id', 'name']),
            // Digest recipients picker (Settings drawer).
            'staff' => DB::table('users')->whereNull('deleted_at')->where('statut', 1)->whereNotNull('email')
                ->orderBy('username')->get(['id', 'username', 'email']),
        ]);
    }

    /**
     * POST reorder/purchase-orders {lines: [{product_id, product_variant_id,
     * warehouse_id, provider_id, quantity, unit_cost}]}
     *
     * One draft purchase order (statut "ordered": no stock moves until it is
     * received) per supplier + warehouse, built exactly like the purchase
     * form's: quantity and cost in the product's purchase unit, line tax from
     * the product (same math as lib/lineCalc.js recomputeCostLine), no order
     * discount / tax / shipping.
     */
    public function createPurchaseOrders(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'create', Purchase::class);

        $data = $request->validate([
            'lines' => 'required|array|min:1|max:500',
            'lines.*.product_id' => 'required|integer',
            'lines.*.product_variant_id' => 'nullable|integer',
            'lines.*.warehouse_id' => 'required|integer',
            'lines.*.provider_id' => 'required|integer',
            'lines.*.quantity' => 'required|numeric|gt:0',
            'lines.*.unit_cost' => 'nullable|numeric|min:0',
        ], [
            'lines.*.provider_id.required' => __('Choose a supplier for every line.'),
        ]);

        $allowedWarehouses = $this->userWarehouseIds();
        $providers = DB::table('providers')->whereNull('deleted_at')->pluck('name', 'id');
        $products = DB::table('products')->whereNull('deleted_at')
            ->whereIn('id', array_column($data['lines'], 'product_id'))
            ->get(['id', 'type', 'name', 'cost', 'unit_id', 'unit_purchase_id', 'TaxNet', 'tax_method'])
            ->keyBy('id');

        // Group per supplier + warehouse, merging repeated product lines.
        $groups = [];
        foreach ($data['lines'] as $i => $line) {
            $product = $products->get((int) $line['product_id']);
            $warehouseId = (int) $line['warehouse_id'];
            $providerId = (int) $line['provider_id'];

            if (! $product || ! in_array($product->type, ['is_single', 'is_variant'], true)) {
                return $this->lineError($i, __('This product can no longer be ordered.'));
            }
            if (! in_array($warehouseId, $allowedWarehouses, true)) {
                return $this->lineError($i, __('You do not have access to this warehouse.'));
            }
            if (! $providers->has($providerId)) {
                return $this->lineError($i, __('Choose a supplier for every line.'));
            }
            $variantId = ! empty($line['product_variant_id']) ? (int) $line['product_variant_id'] : null;
            if ($product->type === 'is_variant' && ! $variantId) {
                return $this->lineError($i, __('This product can no longer be ordered.'));
            }

            $gKey = $providerId.':'.$warehouseId;
            $lKey = $product->id.':'.($variantId ?? 0);
            $groups[$gKey]['provider_id'] = $providerId;
            $groups[$gKey]['warehouse_id'] = $warehouseId;
            $existing = $groups[$gKey]['lines'][$lKey] ?? null;
            $groups[$gKey]['lines'][$lKey] = [
                'product' => $product,
                'variant_id' => $variantId,
                'quantity' => round((float) $line['quantity'] + (float) ($existing['quantity'] ?? 0), 4),
                // Cost per PURCHASE unit; products.cost is per base unit.
                'unit_cost' => isset($line['unit_cost']) && $line['unit_cost'] !== '' && $line['unit_cost'] !== null
                    ? (float) $line['unit_cost']
                    : (float) ($existing['unit_cost'] ?? round((float) $product->cost * $this->purchaseFactor($product), 4)),
            ];
        }

        $created = [];
        DB::transaction(function () use ($groups, $providers, &$created) {
            $purchases = app(PurchasesController::class);
            $warehouseNames = DB::table('warehouses')->pluck('name', 'id');

            foreach ($groups as $group) {
                $details = [];
                $grandTotal = 0.0;
                foreach ($group['lines'] as $line) {
                    $p = $line['product'];
                    $tax = (float) ($p->TaxNet ?? 0);
                    $method = (string) ($p->tax_method ?: '1');
                    $cost = $line['unit_cost'];
                    // lib/lineCalc.js recomputeCostLine with no discount.
                    $taxe = $method === '1' ? round($tax * $cost / 100, 6) : round($cost * $tax / 100, 6);
                    $netCost = $method === '1' ? $cost : $cost - $taxe;
                    $subtotal = round($line['quantity'] * $netCost + $taxe * $line['quantity'], 2);
                    $grandTotal = round($grandTotal + $subtotal, 2);

                    $details[] = [
                        'quantity' => $line['quantity'],
                        'cost' => $cost,
                        'purchase_unit_id' => $p->unit_purchase_id ?: $p->unit_id,
                        'TaxNet' => $tax,
                        'tax_method' => $method,
                        'discount' => 0,
                        'discount_method' => '1',
                        'product_id' => $p->id,
                        'product_variant_id' => $line['variant_id'],
                        'total' => $subtotal,
                        'imei_number' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                $order = new Purchase;
                $order->date = now()->toDateString();
                $order->time = now()->toTimeString();
                $order->Ref = $purchases->getNumberOrder();
                $order->provider_id = $group['provider_id'];
                $order->warehouse_id = $group['warehouse_id'];
                $order->GrandTotal = $grandTotal;
                $order->tax_rate = 0;
                $order->TaxNet = 0;
                $order->discount = 0;
                $order->shipping = 0;
                $order->statut = 'ordered';
                $order->payment_statut = 'unpaid';
                $order->notes = __('Created from Reorder suggestions.');
                $order->user_id = auth()->id();
                $order->save();

                foreach ($details as &$d) {
                    $d['purchase_id'] = $order->id;
                }
                unset($d);
                \App\Models\PurchaseDetail::insert($details);

                $created[] = [
                    'id' => $order->id,
                    'Ref' => $order->Ref,
                    'provider' => $providers[$group['provider_id']] ?? null,
                    'warehouse' => $warehouseNames[$group['warehouse_id']] ?? null,
                    'lines' => count($details),
                    'total' => $grandTotal,
                ];
            }
        });

        \App\Services\Reorder\ReorderDigest::bust();

        return response()->json(['success' => true, 'purchases' => $created]);
    }

    /** Base units in one purchase unit of the product. */
    private function purchaseFactor($product): float
    {
        $unit = DB::table('units')->where('id', $product->unit_purchase_id ?: $product->unit_id)->first(['operator', 'operator_value']);
        $value = (float) ($unit->operator_value ?? 1) ?: 1;

        return ($unit->operator ?? '*') === '/' ? 1 / $value : $value;
    }

    private function lineError(int $index, string $message)
    {
        return response()->json(['message' => $message, 'line' => $index], 422);
    }

    /** GET reorder/settings — the global defaults. */
    public function settings(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'create', Purchase::class);

        return response()->json(['defaults' => ReorderSettings::defaults()]);
    }

    /** PUT reorder/settings {sales_window_days, lead_time_days, safety_days, coverage_days} */
    public function updateSettings(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'create', Purchase::class);

        $request->validate([
            'sales_window_days' => 'nullable|integer|min:7|max:365',
            'lead_time_days' => 'nullable|integer|min:0|max:365',
            'safety_days' => 'nullable|integer|min:0|max:365',
            'coverage_days' => 'nullable|integer|min:1|max:365',
            'digest_enabled' => 'nullable|boolean',
            'digest_hour' => 'nullable|integer|min:0|max:23',
            'digest_user_ids' => 'nullable|array',
            'digest_user_ids.*' => 'integer',
        ]);

        $defaults = ReorderSettings::saveDefaults($request->only(array_keys(ReorderSettings::DEFAULTS)));
        \App\Services\Reorder\ReorderDigest::bust();

        return response()->json(['defaults' => $defaults]);
    }

    /** POST reorder/digest/send — email the digest now (to check it arrives). */
    public function sendDigest(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'create', Purchase::class);

        if (ReorderSettings::defaults()['digest_user_ids'] === []) {
            return response()->json(['message' => __('Choose who receives the digest, then save.')], 422);
        }

        return response()->json(['sent' => \App\Services\Reorder\ReorderDigest::sendIfDue(true)]);
    }
}
