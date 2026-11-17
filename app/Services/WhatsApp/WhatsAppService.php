<?php

namespace App\Services\WhatsApp;

use App\Jobs\WhatsApp\SendWhatsAppMessageJob;
use App\Models\OnlineOrder;
use App\Models\PaymentSale;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppSetting;
use App\utils\helpers;
use Illuminate\Support\Str;
use Throwable;

/**
 * Business-level WhatsApp operations on top of CloudApiClient:
 *
 *  - queue()/deliver(): create a delivery-log row and send it (now or via
 *    the queue), storing Meta's message id / error on the row.
 *  - handleEvent(): rides the WebhookDispatcher fan-out to send the
 *    automation messages (invoice created, payment received).
 *  - notifyOrder(): online-store order status notifications.
 *  - processWebhook(): applies Meta's status callbacks + inbound messages
 *    to the delivery log.
 *
 * Every public entry point swallows its own exceptions: WhatsApp must never
 * break a sale, a payment or an order update.
 */
class WhatsAppService
{
    // ------------------------------------------------------------------
    // Availability
    // ------------------------------------------------------------------

    /** Read-only lookup — never inserts the settings row from hot paths. */
    public static function settings(): ?WhatsAppSetting
    {
        try {
            return WhatsAppSetting::query()->first();
        } catch (Throwable $e) {
            return null; // table not migrated yet
        }
    }

    public static function isActive(): bool
    {
        $s = self::settings();

        return $s ? $s->isActive() : false;
    }

    // ------------------------------------------------------------------
    // Phone normalisation
    // ------------------------------------------------------------------

    /**
     * Meta wants digits only, in international format without "+".
     * "00" prefixes are dropped, a leading "0" is replaced by the default
     * country code when one is configured. Returns null when nothing usable
     * is left.
     */
    public static function normalizePhone(?string $phone, ?WhatsAppSetting $settings = null): ?string
    {
        $raw = trim((string) $phone);
        if ($raw === '' || $raw === 'null') {
            return null;
        }
        $hasPlus = str_starts_with($raw, '+');
        $digits = preg_replace('/\D+/', '', $raw);
        if ($digits === '') {
            return null;
        }
        if (! $hasPlus && str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        } elseif (! $hasPlus && str_starts_with($digits, '0')) {
            $settings = $settings ?: self::settings();
            $cc = preg_replace('/\D+/', '', (string) ($settings?->default_country_code ?? ''));
            if ($cc !== '') {
                $digits = $cc.ltrim($digits, '0');
            }
        }

        return strlen($digits) >= 7 ? $digits : null;
    }

    // ------------------------------------------------------------------
    // Sending
    // ------------------------------------------------------------------

    /**
     * Log + send immediately. Returns the log row (status sent|failed).
     * Used by the Test button and the manual "send via API" actions where
     * the admin wants instant feedback.
     */
    public static function sendNow(string $phone, string $body, array $meta = []): WhatsAppMessage
    {
        $log = self::log($phone, $body, $meta);
        self::deliver($log);

        return $log;
    }

    /** Log + push to the queue (automations). */
    public static function queue(string $phone, string $body, array $meta = []): ?WhatsAppMessage
    {
        try {
            $log = self::log($phone, $body, $meta);
            SendWhatsAppMessageJob::dispatch($log->id)->onQueue('webhooks');

            return $log;
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    protected static function log(string $phone, string $body, array $meta): WhatsAppMessage
    {
        return WhatsAppMessage::create([
            'direction' => 'out',
            'phone' => $phone,
            'recipient_name' => $meta['recipient_name'] ?? null,
            'event' => $meta['event'] ?? 'manual',
            'reference_type' => $meta['reference_type'] ?? null,
            'reference_id' => $meta['reference_id'] ?? null,
            'body' => $body,
            'status' => WhatsAppMessage::STATUS_QUEUED,
        ]);
    }

    /**
     * Perform the actual API call for a log row and persist the outcome.
     * Returns true on success. Safe to call again on a failed row (retry).
     */
    public static function deliver(WhatsAppMessage $log): bool
    {
        $settings = self::settings();
        if (! $settings || ! $settings->isConfigured()) {
            $log->fill([
                'status' => WhatsAppMessage::STATUS_FAILED,
                'error_code' => 'not_configured',
                'error_message' => 'WhatsApp is not configured. Add the Phone Number ID and access token under Integrations → WhatsApp.',
            ])->save();

            return false;
        }
        if (! $settings->enabled && ($log->event ?? '') !== 'test') {
            $log->fill([
                'status' => WhatsAppMessage::STATUS_FAILED,
                'error_code' => 'disabled',
                'error_message' => 'WhatsApp integration is disabled. Turn it on under Integrations → WhatsApp.',
            ])->save();

            return false;
        }

        $to = self::normalizePhone($log->phone, $settings);
        if (! $to) {
            $log->fill([
                'status' => WhatsAppMessage::STATUS_FAILED,
                'error_code' => 'invalid_phone',
                'error_message' => 'The phone number "'.$log->phone.'" is empty or not a valid international number.',
            ])->save();

            return false;
        }

        $result = CloudApiClient::make($settings)->sendText($to, (string) $log->body);

        if ($result['ok']) {
            $log->fill([
                'phone' => $to,
                'wa_message_id' => $result['message_id'] ?? null,
                'status' => WhatsAppMessage::STATUS_SENT,
                'sent_at' => now(),
                'error_code' => null,
                'error_message' => null,
                'response' => $result['data'] ?? null,
            ])->save();

            return true;
        }

        $log->fill([
            'phone' => $to,
            'status' => WhatsAppMessage::STATUS_FAILED,
            'error_code' => (string) ($result['code'] ?? 'error'),
            'error_message' => trim(($result['error'] ?? 'Send failed').(isset($result['hint']) ? ' '.$result['hint'] : '')),
            'response' => $result['raw'] ?? ['error' => $result['error'] ?? null, 'meta_message' => $result['meta_message'] ?? null],
        ])->save();

        return false;
    }

    // ------------------------------------------------------------------
    // Automations
    // ------------------------------------------------------------------

    /**
     * Rides the WebhookDispatcher fan-out (same shape as Xero/Mailchimp).
     * Runs inside the queued DispatchWebhooks listener, so the DB work here
     * is off the request path already.
     */
    public static function handleEvent(string $event, array $payload): void
    {
        try {
            $settings = self::settings();
            if (! $settings || ! $settings->isActive()) {
                return;
            }

            $id = (int) ($payload['id'] ?? 0);
            if (! $id) {
                return;
            }

            if ($event === 'sale.created' && $settings->automationEnabled('sale_created')) {
                $sale = Sale::with('client')->whereNull('deleted_at')->find($id);
                if ($sale) {
                    self::sendSaleMessage($sale, $settings->automationTemplate('sale_created'), 'sale.created', $settings);
                }
            } elseif ($event === 'payment.created' && $settings->automationEnabled('payment_created')) {
                $payment = PaymentSale::find($id);
                $sale = $payment ? Sale::with('client')->whereNull('deleted_at')->find($payment->sale_id) : null;
                if ($sale) {
                    self::sendSaleMessage($sale, $settings->automationTemplate('payment_created'), 'payment.created', $settings, [
                        'paid_amount' => (float) $payment->montant, // this payment, not the running total
                    ], ['reference_type' => 'payment_sale', 'reference_id' => $payment->id]);
                }
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** Online store order status change (confirmed / shipped / delivered). */
    public static function notifyOrder(OnlineOrder $order, string $status): void
    {
        try {
            $settings = self::settings();
            $key = 'order_'.$status;
            if (! $settings || ! $settings->isActive() || ! isset(WhatsAppSetting::AUTOMATIONS[$key]) || ! $settings->automationEnabled($key)) {
                return;
            }

            $order->loadMissing('client');
            $phone = $order->customer_phone ?: ($order->client->phone ?? null);
            if (! $phone) {
                return;
            }
            $name = $order->customer_name ?: ($order->client->name ?? '');
            $business = Setting::whereNull('deleted_at')->value('CompanyName');

            $body = self::render($settings->automationTemplate($key), [
                'contact_name' => $name,
                'business_name' => $business,
                'order_number' => $order->ref,
                'total_amount' => self::money((float) $order->total, null),
                'status' => $status,
            ]);

            self::queue($phone, $body, [
                'recipient_name' => $name,
                'event' => 'order.'.$status,
                'reference_type' => 'online_order',
                'reference_id' => $order->id,
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    protected static function sendSaleMessage(Sale $sale, string $template, string $event, WhatsAppSetting $settings, array $overrides = [], array $meta = []): void
    {
        $client = $sale->client;
        $phone = $client->phone ?? null;
        if (! $phone) {
            return;
        }

        $vars = self::saleVariables($sale, $overrides);
        $body = self::render($template, $vars);

        self::queue($phone, $body, array_merge([
            'recipient_name' => $client->name ?? null,
            'event' => $event,
            'reference_type' => 'sale',
            'reference_id' => $sale->id,
        ], $meta));
    }

    /** The same tag set the existing SMS/WhatsApp-link templates use. */
    public static function saleVariables(Sale $sale, array $overrides = []): array
    {
        $doc = helpers::Get_Document_Currency($sale);
        $rate = (float) ($doc['rate'] ?? 1);
        $symbol = $doc['symbol'] ?? '';
        $business = Setting::whereNull('deleted_at')->value('CompanyName');

        $total = (float) $sale->GrandTotal;
        $paid = array_key_exists('paid_amount', $overrides) ? (float) $overrides['paid_amount'] : (float) $sale->paid_amount;
        $due = $total - (float) $sale->paid_amount;

        return [
            'contact_name' => $sale->client->name ?? '',
            'business_name' => $business,
            'invoice_number' => $sale->Ref,
            'invoice_url' => $sale->shareUrl(),
            'total_amount' => self::money($total * $rate, $symbol),
            'paid_amount' => self::money($paid * $rate, $symbol),
            'due_amount' => self::money(max($due, 0) * $rate, $symbol),
        ];
    }

    public static function money(float $amount, ?string $symbol): string
    {
        if ($symbol === null) {
            $symbol = (new helpers)->Get_Currency();
        }

        return trim($symbol.' '.number_format($amount, helpers::price_decimals(), '.', ','));
    }

    public static function render(string $template, array $vars): string
    {
        foreach ($vars as $key => $value) {
            $template = str_replace('{'.$key.'}', (string) $value, $template);
        }

        return trim($template);
    }

    // ------------------------------------------------------------------
    // Inbound webhook
    // ------------------------------------------------------------------

    /**
     * Apply a parsed webhook body. Meta batches: entry[].changes[].value
     * with either `statuses[]` (delivery receipts) or `messages[]`.
     * Returns counts for logging/tests.
     */
    public static function processWebhook(array $body): array
    {
        $statuses = 0;
        $messages = 0;

        foreach ((array) ($body['entry'] ?? []) as $entry) {
            foreach ((array) ($entry['changes'] ?? []) as $change) {
                $value = (array) ($change['value'] ?? []);

                foreach ((array) ($value['statuses'] ?? []) as $st) {
                    if (self::applyStatus($st)) {
                        $statuses++;
                    }
                }

                $contacts = [];
                foreach ((array) ($value['contacts'] ?? []) as $c) {
                    $contacts[$c['wa_id'] ?? ''] = $c['profile']['name'] ?? null;
                }
                foreach ((array) ($value['messages'] ?? []) as $msg) {
                    self::storeInbound($msg, $contacts);
                    $messages++;
                }
            }
        }

        try {
            WhatsAppSetting::query()->update(['last_webhook_at' => now()]);
        } catch (Throwable $e) {
            // ignore
        }

        return ['statuses' => $statuses, 'messages' => $messages];
    }

    protected static function applyStatus(array $st): bool
    {
        $id = $st['id'] ?? null;
        $status = strtolower((string) ($st['status'] ?? ''));
        if (! $id || ! $status) {
            return false;
        }

        $log = WhatsAppMessage::where('wa_message_id', $id)->first();
        if (! $log) {
            return false;
        }

        $ts = isset($st['timestamp']) ? \Carbon\Carbon::createFromTimestamp((int) $st['timestamp']) : now();
        $rank = ['queued' => 0, 'sent' => 1, 'delivered' => 2, 'read' => 3];

        if ($status === 'failed') {
            $err = $st['errors'][0] ?? [];
            [$friendly, $hint] = CloudApiClient::explain(
                (string) ($err['code'] ?? 'failed'),
                null,
                (string) ($err['message'] ?? $err['title'] ?? 'Delivery failed'),
                (string) ($err['error_data']['details'] ?? '')
            );
            $log->fill([
                'status' => WhatsAppMessage::STATUS_FAILED,
                'error_code' => (string) ($err['code'] ?? 'failed'),
                'error_message' => trim($friendly.' '.$hint),
                'response' => $st,
            ])->save();

            return true;
        }

        if (! isset($rank[$status])) {
            return false;
        }
        // Never move backwards (Meta can deliver "sent" after "delivered").
        if (($rank[$log->status] ?? 0) > $rank[$status]) {
            return true;
        }

        $log->status = $status;
        if ($status === 'sent' && ! $log->sent_at) {
            $log->sent_at = $ts;
        }
        if ($status === 'delivered') {
            $log->delivered_at = $log->delivered_at ?: $ts;
        }
        if ($status === 'read') {
            $log->read_at = $log->read_at ?: $ts;
            $log->delivered_at = $log->delivered_at ?: $ts;
        }
        $log->save();

        return true;
    }

    protected static function storeInbound(array $msg, array $contacts): void
    {
        $id = $msg['id'] ?? null;
        if ($id && WhatsAppMessage::where('wa_message_id', $id)->exists()) {
            return; // Meta retries deliveries; stay idempotent
        }

        $type = (string) ($msg['type'] ?? 'unknown');
        $body = match ($type) {
            'text' => (string) ($msg['text']['body'] ?? ''),
            'button' => (string) ($msg['button']['text'] ?? ''),
            'interactive' => (string) ($msg['interactive']['button_reply']['title'] ?? $msg['interactive']['list_reply']['title'] ?? ''),
            default => '['.$type.' message]',
        };

        $from = (string) ($msg['from'] ?? '');

        $row = WhatsAppMessage::create([
            'direction' => 'in',
            'wa_message_id' => $id,
            'phone' => $from,
            'recipient_name' => $contacts[$from] ?? null,
            'event' => 'inbound.'.$type,
            'body' => $body,
            'status' => WhatsAppMessage::STATUS_RECEIVED,
            'response' => $msg,
            'sent_at' => isset($msg['timestamp']) ? \Carbon\Carbon::createFromTimestamp((int) $msg['timestamp']) : now(),
        ]);

        // CRM: a reply from a lead / customer number joins their conversation.
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('crm_messages')) {
                app(\App\Services\Crm\CrmMessageService::class)->matchInbound($row);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }
}
