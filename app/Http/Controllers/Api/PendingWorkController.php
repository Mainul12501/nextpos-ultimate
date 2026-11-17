<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EcommerceClient;
use App\Models\Message;
use App\Models\OnlineOrder;
use App\Models\OnlineOrderReturn;
use App\Models\Product;
use App\Models\ProductQuestion;
use App\Models\ProductReview;
use App\Models\StoreQuoteRequest;
use App\Models\StoreSetting;
use App\Models\WalletWithdrawal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

/**
 * Everything waiting on a member of staff, in one payload: the notification
 * bell renders the list, the sidebar renders the same numbers as badges.
 *
 * Counts are DERIVED from the live rows (pending status / unread flag), never
 * stored — so an item disappears by itself the moment it is processed, with no
 * "mark as read" bookkeeping to drift out of sync.
 */
class PendingWorkController extends Controller
{
    use \App\Traits\ScopesWarehouseAccess;

    public function index(Request $request)
    {
        $user = $request->user('api');
        abort_unless($user, 401);

        $items = [];

        foreach ($this->sources() as $key => $source) {
            // Skip anything this user may not see, and anything whose table is
            // not present (modules differ between installs / upgrade states).
            if (! empty($source['table']) && ! Schema::hasTable($source['table'])) {
                continue;
            }
            if (! $this->allows($user, $source)) {
                continue;
            }

            try {
                $count = (int) ($source['count'])();
            } catch (\Throwable $e) {
                continue;
            }

            if ($count <= 0) {
                continue;
            }

            $items[] = [
                'key' => $key,
                // The admin SPA translates from its own bundle, so hand it the
                // key rather than a PHP-side string.
                'label_key' => $source['label'],
                'count' => $count,
                'route' => $source['route'],
                'severity' => $source['severity'] ?? 'info',
            ];
        }

        // Reorder digest without cron: the bell polls regularly, so the first
        // poll past the chosen hour sends today's digest after the response.
        try {
            if (\App\Services\Reorder\ReorderDigest::due()) {
                app()->terminating(fn () => \App\Services\Reorder\ReorderDigest::sendIfDue());
            }
        } catch (\Throwable $e) {
            // never break the bell
        }

        return response()->json([
            'items' => $items,
            'total' => array_sum(array_column($items, 'count')),
        ]);
    }

    /**
     * key => [count closure, label key, SPA route, permission/policy, table].
     * `permission` is a plain permission name; `policy` is [ability, class].
     */
    /**
     * Rental Management: overdue rent, items not back, contracts ending,
     * holds about to lapse, urgent repairs.
     */
    private function rentalSources(): array
    {
        $now = \Carbon\Carbon::now();

        return [
            'rental_overdue_invoices' => [
                'label' => 'Rental_Bell_Overdue_Invoices',
                'route' => '/rental/invoices?stage=overdue',
                'permission' => 'rnt_billing_view',
                'table' => 'rental_invoices',
                'severity' => 'danger',
                'count' => fn () => \App\Models\RentalInvoice::query()
                    ->join('rental_contracts', 'rental_contracts.id', '=', 'rental_invoices.rental_contract_id')
                    ->whereIn('rental_invoices.status', ['unpaid', 'partial'])
                    ->whereRaw('DATE_ADD(rental_invoices.due_date, INTERVAL rental_contracts.grace_days DAY) < ?', [$now->toDateString()])
                    ->count(),
            ],
            'rental_late_returns' => [
                'label' => 'Rental_Bell_Late_Returns',
                'route' => '/rental/contracts?stage=overdue',
                'permission' => 'rnt_contracts_view',
                'table' => 'rental_contracts',
                'severity' => 'danger',
                'count' => fn () => \App\Models\RentalContract::whereNull('deleted_at')->where('status', 'active')
                    ->where('end_date', '<', $now)
                    ->whereHas('items', fn ($q) => $q->whereNull('returned_at'))->count(),
            ],
            'rental_expiring' => [
                'label' => 'Rental_Bell_Expiring',
                'route' => '/rental/contracts?stage=expiring',
                'permission' => 'rnt_contracts_view',
                'table' => 'rental_contracts',
                'severity' => 'warning',
                'count' => fn () => \App\Models\RentalContract::whereNull('deleted_at')->where('status', 'active')
                    ->whereNull('renewal_id')->where('start_date', '<=', $now)
                    ->whereBetween('end_date', [$now, $now->copy()->addDays((int) \App\Models\RentalSetting::current()->expiring_soon_days)])->count(),
            ],
            'rental_holds_ending' => [
                'label' => 'Rental_Bell_Holds_Ending',
                'route' => '/rental/reservations?stage=expiring',
                'permission' => 'rnt_reservations_view',
                'table' => 'rental_reservations',
                'severity' => 'warning',
                'count' => fn () => \App\Models\RentalReservation::whereNull('deleted_at')->where('status', 'held')
                    ->whereBetween('hold_until', [$now, $now->copy()->addDay()])->count(),
            ],
            'rental_urgent_maintenance' => [
                'label' => 'Rental_Bell_Urgent_Maintenance',
                'route' => '/rental/maintenance',
                'permission' => 'rnt_maintenance',
                'table' => 'rental_maintenance',
                'severity' => 'warning',
                'count' => fn () => \App\Models\RentalMaintenance::whereNull('deleted_at')
                    ->whereIn('status', ['open', 'scheduled', 'in_progress'])->whereIn('priority', ['urgent', 'high'])
                    ->whereHas('item')->count(),
            ],
            'crm_my_stale_leads' => [
                'label' => 'Crm_Bell_My_Stale_Leads',
                'route' => '/crm/leads?owner=me&quick=stale',
                'permission' => 'crm_leads_view',
                'table' => 'crm_leads',
                'severity' => 'warning',
                'count' => fn () => \App\Models\CrmLead::whereNull('deleted_at')->whereNull('client_id')
                    ->where('owner_id', auth()->id())
                    ->whereIn('crm_lead_status_id', \App\Models\CrmLeadStatus::where('kind', 'open')->pluck('id'))
                    ->where(fn ($q) => $q->whereNull('last_activity_at')->orWhere('last_activity_at', '<',
                        now()->subDays(max(1, (int) \App\Models\CrmSetting::current()->stale_lead_days))))
                    ->count(),
            ],
            'crm_unread_replies' => [
                'label' => 'Crm_Bell_Unread_Replies',
                'route' => '/crm/inbox',
                'permission' => 'crm_messages',
                'table' => 'crm_messages',
                'severity' => 'info',
                'count' => fn () => \App\Models\CrmMessage::where('direction', 'in')->whereNull('read_at')
                    ->where('owner_id', auth()->id())->count(),
            ],
            'crm_my_overdue_tasks' => [
                'label' => 'Crm_Bell_My_Overdue_Tasks',
                'route' => '/crm/my-day',
                'permission' => 'crm_activities_view',
                'table' => 'crm_activities',
                'severity' => 'danger',
                // Per person: only what is assigned to whoever is looking.
                'count' => fn () => \App\Models\CrmActivity::whereNull('deleted_at')->where('owner_id', auth()->id())
                    ->where('status', 'open')->where('due_at', '<', \Carbon\Carbon::now())->count(),
            ],
            'crm_overdue_deals' => [
                'label' => 'Crm_Bell_Overdue_Deals',
                'route' => '/crm/deals?quick=overdue',
                'permission' => 'crm_deals_view',
                'table' => 'crm_deals',
                'severity' => 'warning',
                'count' => fn () => \App\Models\CrmDeal::whereNull('deleted_at')->where('status', 'open')
                    ->whereDate('expected_close_date', '<', \Carbon\Carbon::today())->count(),
            ],
            'crm_unassigned_leads' => [
                'label' => 'Crm_Bell_Unassigned_Leads',
                'route' => '/crm/leads?owner=none&status=open',
                'permission' => 'crm_leads_view',
                'table' => 'crm_leads',
                'severity' => 'warning',
                'count' => fn () => \App\Models\CrmLead::whereNull('deleted_at')->whereNull('owner_id')
                    ->whereIn('crm_lead_status_id', \App\Models\CrmLeadStatus::where('kind', 'open')->pluck('id'))->count(),
            ],
            'rental_customer_requests' => [
                'label' => 'Rental_Bell_Customer_Requests',
                'route' => '/rental/maintenance?stage=requests',
                'permission' => 'rnt_maintenance',
                'table' => 'rental_maintenance',
                'severity' => 'warning',
                // Only requests nobody has picked up yet; scheduling or starting one clears it.
                'count' => fn () => \App\Models\RentalMaintenance::whereNull('deleted_at')
                    // Urgent / high ones are already counted as urgent maintenance.
                    ->where('source', 'portal')->where('status', 'open')->whereNotIn('priority', ['urgent', 'high'])
                    ->whereHas('item')->count(),
            ],
        ];
    }

    private function sources(): array
    {
        return array_merge($this->rentalSources(), [
            'reorder_needed' => [
                'label' => 'Reorder_Bell_Needed',
                'route' => '/purchases/reorder',
                'permission' => 'Purchases_add',
                'table' => 'product_warehouse',
                'severity' => 'warning',
                // Smart reordering: products at or below their reorder point
                // in the user's warehouses (cached a few minutes).
                'count' => fn () => \App\Services\Reorder\ReorderDigest::count($this->userWarehouseIds()),
            ],
            'quantity_alerts' => [
                'label' => 'Product_Quantity_Alerts',
                'route' => '/reports/quantity-alerts',
                'permission' => 'Reports_quantity_alerts',
                'table' => 'product_warehouse',
                'severity' => 'warning',
                // Same query as ReportController@count_quantity_alert: stock
                // lives on product_warehouse, and only the user's warehouses
                // count.
                'count' => fn () => \App\Models\product_warehouse::join('products', 'product_warehouse.product_id', '=', 'products.id')
                    ->whereRaw('qte <= stock_alert')
                    ->whereIn('product_warehouse.warehouse_id', $this->userWarehouseIds())
                    ->count(),
            ],
            'new_orders' => [
                'label' => 'Pending_Online_Orders',
                'route' => '/store/orders',
                'permission' => 'Orders_view',
                'table' => 'online_orders',
                'count' => fn () => OnlineOrder::where('status', 'pending')->count(),
            ],
            'payment_proofs' => [
                'label' => 'Payment_Proofs_To_Verify',
                'route' => '/store/orders?proof=pending',
                'permission' => 'Orders_view',
                'table' => 'online_order_payment_proofs',
                'count' => fn () => \App\Models\OnlineOrderPaymentProof::where('status', 'pending')->count(),
            ],
            'pending_customers' => [
                'label' => 'Pending_Customers',
                'route' => '/store/pending-customers',
                'policy' => ['view', StoreSetting::class],
                'table' => 'ecommerce_clients',
                'count' => fn () => EcommerceClient::whereNull('deleted_at')->where('status', 0)->count(),
            ],
            'return_requests' => [
                'label' => 'Return_Requests',
                'route' => '/store/returns',
                'permission' => 'Orders_view',
                'table' => 'online_order_returns',
                'count' => fn () => OnlineOrderReturn::where('status', 'requested')->count(),
            ],
            'quote_requests' => [
                'label' => 'Quote_Requests',
                'route' => '/store/quote-requests',
                'permission' => 'Orders_view',
                'table' => 'store_quote_requests',
                'count' => fn () => StoreQuoteRequest::where('status', 'new')->count(),
            ],
            'portal_quote_requests' => [
                // Quotations a portal customer asked for and nobody has priced
                // yet. `source` keeps staff-created drafts (also 'pending')
                // out of the count; it clears itself once the quote is sent.
                'label' => 'Portal_Quotation_Requests',
                'route' => '/quotations?source=portal&statut=pending',
                'permission' => 'Quotations_view',
                'table' => 'quotations',
                'count' => fn () => \App\Models\Quotation::whereNull('deleted_at')
                    ->where('source', 'portal')
                    ->where('statut', 'pending')
                    ->whereIn('warehouse_id', $this->userWarehouseIds())
                    // Same row scope as the list the badge links to, so the
                    // number never points at an empty page.
                    ->when(! optional(auth()->user())->hasRecordView(),
                        fn ($q) => $q->where('user_id', optional(auth()->user())->id))
                    ->count(),
            ],
            'product_reviews' => [
                'label' => 'Product_Reviews',
                'route' => '/store/reviews',
                'policy' => ['view', StoreSetting::class],
                'table' => 'product_reviews',
                'count' => fn () => ProductReview::where('status', 'pending')->count(),
            ],
            'product_questions' => [
                'label' => 'Customer_Questions',
                // The list opens pre-filtered on the unanswered ones, which is
                // exactly what the count is.
                'route' => '/store/questions?unanswered=1',
                'policy' => ['view', StoreSetting::class],
                'table' => 'product_questions',
                'count' => fn () => ProductQuestion::whereNull('answered_at')->count(),
            ],
            'messages' => [
                'label' => 'Messages',
                'route' => '/store/messages',
                'permission' => 'Messages_view',
                'table' => 'messages',
                'count' => fn () => Message::where('is_read', 0)->count(),
            ],
            'wallet_withdrawals' => [
                'label' => 'Pending_Withdrawals',
                // The dashboard's overview lists the pending requests with the
                // approve/reject buttons; it is gated on `ewallet`, so the
                // notification has to be too or it links to a refusal.
                'route' => '/ewallet/dashboard',
                'permission' => 'ewallet',
                'table' => 'wallet_withdrawals',
                'count' => fn () => WalletWithdrawal::where('status', 'pending')->count(),
            ],
        ]);
    }

    /** Permission name or policy ability, whichever the source declares. */
    private function allows($user, array $source): bool
    {
        if (! empty($source['policy'])) {
            [$ability, $class] = $source['policy'];

            return Gate::forUser($user)->allows($ability, $class);
        }

        if (! empty($source['permission'])) {
            try {
                $permission = \App\Models\Permission::where('name', $source['permission'])->first();

                return $permission ? (bool) $user->hasRole($permission->roles) : false;
            } catch (\Throwable $e) {
                return false;
            }
        }

        return true;
    }
}
