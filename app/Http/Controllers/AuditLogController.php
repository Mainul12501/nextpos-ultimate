<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Setting;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only audit trail + its two settings. There is deliberately no delete
 * endpoint: entries only leave through the retention window (audit:prune).
 */
class AuditLogController extends Controller
{
    private const EXPORT_CAP = 5000;

    /** Child records shown in a parent's history: [class, table, foreign key]. */
    private const RELATED = [
        'App\\Models\\Sale' => [['App\\Models\\PaymentSale', 'payment_sales', 'sale_id']],
        'App\\Models\\Purchase' => [['App\\Models\\PaymentPurchase', 'payment_purchases', 'purchase_id']],
        'App\\Models\\SaleReturn' => [['App\\Models\\PaymentSaleReturns', 'payment_sale_returns', 'sale_return_id']],
        'App\\Models\\PurchaseReturn' => [['App\\Models\\PaymentPurchaseReturns', 'payment_purchase_returns', 'purchase_return_id']],
        'App\\Models\\Product' => [['App\\Models\\ProductVariant', 'product_variants', 'product_id']],
    ];

    /** Full class, or a short name (?module=Product) for deep links. */
    private function moduleClass(string $module): string
    {
        return str_contains($module, '\\') ? $module : 'App\\Models\\'.$module;
    }

    /**
     * Child entries: matched through the live table (payments are soft
     * deleted, so their ids survive) or through the foreign key recorded in
     * the entry itself (variants are hard deleted and recreated on edit).
     */
    private function whereChildOf($q, string $childType, string $table, string $fk, int $parentId)
    {
        $q->where('auditable_type', $childType)->where(function ($qq) use ($table, $fk, $parentId) {
            if (Schema::hasTable($table)) {
                $qq->whereIn('auditable_id', fn ($sub) => $sub->select('id')->from($table)->where($fk, $parentId));
            }
            $qq->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(new_values, '$.{$fk}')) = ?", [(string) $parentId])
                ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(old_values, '$.{$fk}')) = ?", [(string) $parentId]);
        });
    }

    public function index(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'audit_log', Setting::class);

        if (! Schema::hasTable('audit_logs')) {
            return response()->json(['logs' => [], 'totalRows' => 0, 'users' => [], 'modules' => [], 'summary' => []]);
        }

        $base = function () use ($request) {
            return AuditLog::query()
                ->when($request->filled('search'), function ($q) use ($request) {
                    $s = trim((string) $request->search);
                    $q->where(function ($qq) use ($s) {
                        $qq->where('auditable_label', 'LIKE', "%{$s}%")
                            ->orWhere('user_name', 'LIKE', "%{$s}%")
                            ->orWhere('ip_address', 'LIKE', "%{$s}%")
                            ->orWhere('url', 'LIKE', "%{$s}%");
                        if (ctype_digit($s)) {
                            $qq->orWhere('auditable_id', (int) $s);
                        }
                    });
                })
                ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', (int) $request->user_id))
                ->when($request->filled('event'), fn ($q) => $q->where('event', $request->event))
                // Record history (module + record_id), optionally with its child
                // records (?related=1: a sale's payments, a product's variants).
                ->when($request->filled('module') && $request->filled('record_id'), function ($q) use ($request) {
                    $type = $this->moduleClass((string) $request->module);
                    $id = (int) $request->record_id;
                    $q->where(function ($qq) use ($type, $id, $request) {
                        $qq->where(fn ($own) => $own->where('auditable_type', $type)->where('auditable_id', $id));
                        if ($request->boolean('related') && isset(self::RELATED[$type])) {
                            foreach (self::RELATED[$type] as [$childType, $table, $fk]) {
                                $qq->orWhere(fn ($child) => $this->whereChildOf($child, $childType, $table, $fk, $id));
                            }
                        }
                    });
                })
                ->when($request->filled('module') && ! $request->filled('record_id'), fn ($q) => $q->where('auditable_type', $this->moduleClass((string) $request->module)))
                ->when(! $request->filled('module') && $request->filled('record_id'), fn ($q) => $q->where('auditable_id', (int) $request->record_id))
                ->when($request->filled('batch_id'), fn ($q) => $q->where('batch_id', $request->batch_id))
                ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->from))
                ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->to));
        };

        $total = $base()->count();

        $summary = [
            'total' => $total,
            'today' => $base()->whereDate('created_at', now()->toDateString())->count(),
            'deletions' => $base()->whereIn('event', ['deleted', 'bulk_deleted'])->count(),
            'users' => $base()->whereNotNull('user_id')->distinct()->count('user_id'),
        ];

        $sortField = $request->get('SortField', 'id');
        if (! in_array($sortField, ['id', 'created_at', 'event', 'user_name', 'auditable_type', 'auditable_id'], true)) {
            $sortField = 'id';
        }
        $sortType = strtolower((string) $request->get('SortType', 'desc')) === 'asc' ? 'asc' : 'desc';

        $limit = (int) ($request->limit ?? 10);
        if ($limit <= 0 || $limit > self::EXPORT_CAP) {
            $limit = self::EXPORT_CAP;
        }
        $page = max(1, (int) $request->get('page', 1));

        $logs = $base()
            ->orderBy($sortField, $sortType)
            ->orderBy('id', $sortType)
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get()
            ->map(function (AuditLog $log) {
                $old = $log->old_values ?: [];
                $new = $log->new_values ?: [];

                return [
                    'id' => $log->id,
                    'created_at' => optional($log->created_at)->toIso8601String(),
                    'user_id' => $log->user_id,
                    'user_name' => $log->user_name,
                    'event' => $log->event,
                    'auditable_type' => $log->auditable_type,
                    'module' => $log->auditable_type ? class_basename($log->auditable_type) : null,
                    'auditable_id' => $log->auditable_id,
                    'auditable_label' => $log->auditable_label,
                    'old_values' => (object) $old,
                    'new_values' => (object) $new,
                    'changed_fields' => array_values(array_unique(array_merge(array_keys($old), array_keys($new)))),
                    'ip_address' => $log->ip_address,
                    'user_agent' => $log->user_agent,
                    'url' => $log->url,
                    'method' => $log->method,
                    'batch_id' => $log->batch_id,
                ];
            });

        // Filter dropdown sources — over the whole log, not the filtered set,
        // so a narrowed view can still be widened again.
        $users = AuditLog::query()
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->selectRaw('user_id as id, MAX(user_name) as name')
            ->orderBy('name')
            ->get();

        $modules = AuditLog::query()
            ->whereNotNull('auditable_type')
            ->distinct()
            ->orderBy('auditable_type')
            ->pluck('auditable_type')
            ->map(fn ($type) => ['value' => $type, 'label' => class_basename($type)])
            ->values();

        return response()->json([
            'logs' => $logs,
            'totalRows' => $total,
            'summary' => $summary,
            'users' => $users,
            'modules' => $modules,
        ]);
    }

    public function settings(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'audit_log', Setting::class);

        $setting = Setting::whereNull('deleted_at')->first();

        return response()->json([
            'enabled' => (bool) ($setting->audit_log_enabled ?? true),
            'retention_days' => $setting->audit_log_retention_days ?? null,
        ]);
    }

    public function updateSettings(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'audit_log', Setting::class);

        $data = $request->validate([
            'enabled' => 'required|boolean',
            'retention_days' => 'nullable|integer|min:1|max:3650',
        ]);

        $setting = Setting::whereNull('deleted_at')->firstOrFail();
        $wasEnabled = (bool) ($setting->audit_log_enabled ?? true);
        $setting->audit_log_enabled = (bool) $data['enabled'];
        $setting->audit_log_retention_days = $data['retention_days'] ?? null;
        $setting->save();

        AuditLogger::flush();

        // The save above ran while recording was off — log the switch-on itself.
        if (! $wasEnabled && $setting->audit_log_enabled) {
            AuditLogger::record('updated', $setting, ['audit_log_enabled' => false], ['audit_log_enabled' => true]);
        }

        return response()->json(['success' => true]);
    }
}
