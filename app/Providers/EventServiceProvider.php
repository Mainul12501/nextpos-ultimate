<?php

namespace App\Providers;

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        \App\Events\SaleCreated::class => [
            \App\Listeners\AccountingV2\GenerateSaleJournal::class,
        ],
        \App\Events\PurchaseCreated::class => [
            \App\Listeners\AccountingV2\GeneratePurchaseJournal::class,
        ],
        \App\Events\ExpenseCreated::class => [
            \App\Listeners\AccountingV2\GenerateExpenseJournal::class,
        ],
        \App\Events\SaleReturned::class => [
            \App\Listeners\AccountingV2\GenerateSaleReturnJournal::class,
        ],
        \App\Events\SaleUpdated::class => [
            \App\Listeners\AccountingV2\GenerateSaleAdjustment::class,
        ],
        \App\Events\PurchaseUpdated::class => [
            \App\Listeners\AccountingV2\GeneratePurchaseAdjustment::class,
        ],
        \App\Events\ExpenseUpdated::class => [
            \App\Listeners\AccountingV2\GenerateExpenseAdjustment::class,
        ],
        \App\Events\SaleDeleted::class => [
            \App\Listeners\AccountingV2\GenerateSaleReversal::class,
        ],
        \App\Events\PurchaseDeleted::class => [
            \App\Listeners\AccountingV2\GeneratePurchaseReversal::class,
        ],
        \App\Events\ExpenseDeleted::class => [
            \App\Listeners\AccountingV2\GenerateExpenseReversal::class,
        ],
        \App\Events\PaymentCreated::class => [
            \App\Listeners\AccountingV2\GeneratePaymentSaleJournal::class,
        ],
        \App\Events\PaymentPurchaseCreated::class => [
            \App\Listeners\AccountingV2\GeneratePaymentPurchaseJournal::class,
        ],
        \App\Events\PaymentDeleted::class => [
            \App\Listeners\AccountingV2\GeneratePaymentSaleReversal::class,
        ],
        \App\Events\PaymentPurchaseDeleted::class => [
            \App\Listeners\AccountingV2\GeneratePaymentPurchaseReversal::class,
        ],
        \App\Events\SaleReturnUpdated::class => [
            \App\Listeners\AccountingV2\GenerateSaleReturnAdjustment::class,
        ],
        \App\Events\SaleReturnDeleted::class => [
            \App\Listeners\AccountingV2\GenerateSaleReturnReversal::class,
        ],
        \App\Events\PurchaseReturnCreated::class => [
            \App\Listeners\AccountingV2\GeneratePurchaseReturnJournal::class,
        ],
        \App\Events\PurchaseReturnUpdated::class => [
            \App\Listeners\AccountingV2\GeneratePurchaseReturnAdjustment::class,
        ],
        \App\Events\PurchaseReturnDeleted::class => [
            \App\Listeners\AccountingV2\GeneratePurchaseReturnReversal::class,
        ],
        \App\Events\PaymentSaleReturnCreated::class => [
            \App\Listeners\AccountingV2\GeneratePaymentSaleReturnJournal::class,
        ],
        \App\Events\PaymentSaleReturnDeleted::class => [
            \App\Listeners\AccountingV2\GeneratePaymentSaleReturnReversal::class,
        ],
        \App\Events\PaymentPurchaseReturnCreated::class => [
            \App\Listeners\AccountingV2\GeneratePaymentPurchaseReturnJournal::class,
        ],
        \App\Events\PaymentPurchaseReturnDeleted::class => [
            \App\Listeners\AccountingV2\GeneratePaymentPurchaseReturnReversal::class,
        ],
        \App\Events\CashRegisterOpened::class => [
            \App\Listeners\AccountingV2\GenerateCashRegisterOpeningJournal::class,
        ],
        \Laravel\Passport\Events\AccessTokenCreated::class => [
            \App\Listeners\Security\RecordLoginSession::class,
        ],
    ];

    /**
     * Events that should also dispatch outgoing webhooks.
     * Kept separate from $listen so existing listener wiring is never touched.
     * The webhook listener is queued — failures here cannot break business flow.
     */
    protected array $webhookListen = [
        \App\Events\SaleCreated::class,
        \App\Events\SaleUpdated::class,
        \App\Events\SaleDeleted::class,
        \App\Events\SaleReturned::class,
        \App\Events\SaleReturnUpdated::class,
        \App\Events\SaleReturnDeleted::class,
        \App\Events\PurchaseCreated::class,
        \App\Events\PurchaseUpdated::class,
        \App\Events\PurchaseDeleted::class,
        \App\Events\PurchaseReturnCreated::class,
        \App\Events\PurchaseReturnUpdated::class,
        \App\Events\PurchaseReturnDeleted::class,
        \App\Events\PaymentCreated::class,
        \App\Events\PaymentDeleted::class,
        \App\Events\PaymentPurchaseCreated::class,
        \App\Events\PaymentPurchaseDeleted::class,
        \App\Events\PaymentSaleReturnCreated::class,
        \App\Events\PaymentSaleReturnDeleted::class,
        \App\Events\PaymentPurchaseReturnCreated::class,
        \App\Events\PaymentPurchaseReturnDeleted::class,
        \App\Events\ExpenseCreated::class,
        \App\Events\ExpenseUpdated::class,
        \App\Events\ExpenseDeleted::class,
        \App\Events\CashRegisterOpened::class,
        \App\Events\ClientCreated::class,
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();

        // Subscribe the universal webhook listener to every domain event it can handle.
        // Additive: does not alter or replace any listener already registered above.
        foreach ($this->webhookListen as $eventClass) {
            Event::listen($eventClass, [\App\Listeners\Webhooks\DispatchWebhooks::class, 'handle']);
            // Telegram: not queued, so it can send right after the response.
            Event::listen($eventClass, [\App\Listeners\Notifications\NotifyTelegram::class, 'handle']);
        }

        // Audit log: staff sign-in / sign-out / failed sign-in.
        Event::listen(\Illuminate\Auth\Events\Login::class, [\App\Listeners\Security\RecordAuthAudit::class, 'handleLogin']);
        Event::listen(\Illuminate\Auth\Events\Logout::class, [\App\Listeners\Security\RecordAuthAudit::class, 'handleLogout']);
        Event::listen(\Illuminate\Auth\Events\Failed::class, [\App\Listeners\Security\RecordAuthAudit::class, 'handleFailed']);

        // client.created — not raised by AccountingV2, so fired here with the
        // same softly-failing lifecycle hook. Mailchimp auto-sync, webhooks and
        // the chat channels all hang off it.
        \App\Models\Client::created(function ($client) {
            try {
                event(new \App\Events\ClientCreated($client));
            } catch (\Throwable $e) {
                report($e);
            }
        });

        // A confirmed online order becomes a Sale; payments recorded on that
        // Sale afterwards (cash on delivery, offline transfer) must show on the
        // order too, or Online Orders keeps saying "pending".
        \App\Models\Sale::updated(function ($sale) {
            try {
                if (! $sale->wasChanged('payment_statut')) {
                    return;
                }
                $status = $sale->payment_statut === 'paid' ? 'paid'
                    : ($sale->payment_statut === 'partial' ? 'partial' : 'pending');
                \App\Models\OnlineOrder::where('sale_id', $sale->id)
                    ->where('payment_status', '!=', 'refunded')
                    ->update(['payment_status' => $status]);
            } catch (\Throwable $e) {
                report($e);
            }
        });
    }
}
