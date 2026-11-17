<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppSetting;
use App\Services\WhatsApp\CloudApiClient;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Http\Request;

/**
 * Integrations → WhatsApp. One page that holds everything the Meta Cloud
 * API needs (Phone Number ID, WABA id, token, app secret, verify token +
 * callback URL), a Test Connection button, a send-test-message button,
 * the automation toggles/templates and the delivery log.
 *
 * Gated by the `whatsapp_settings` permission. Secrets are write-only.
 */
class WhatsAppSettingsController extends Controller
{
    public function show(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', WhatsAppSetting::class);

        $s = WhatsAppSetting::current();

        return response()->json($this->payload($s));
    }

    protected function payload(WhatsAppSetting $s): array
    {
        $defs = [];
        foreach (WhatsAppSetting::AUTOMATIONS as $key => $def) {
            $defs[] = ['key' => $key, 'label' => $def['label'], 'hint' => $def['hint'], 'default_template' => $def['template']];
        }

        return [
            'enabled' => (bool) $s->enabled,
            'configured' => $s->isConfigured(),
            'phone_number_id' => $s->phone_number_id,
            'business_account_id' => $s->business_account_id,
            'api_version' => $s->api_version ?: 'v21.0',
            'default_country_code' => $s->default_country_code,
            'access_token_hint' => $s->access_token ? '…'.substr($s->access_token, -6) : null,
            'app_secret_hint' => $s->app_secret ? '…'.substr($s->app_secret, -4) : null,
            'has_app_secret' => ! empty($s->app_secret),
            'verify_token' => $s->verify_token,
            'webhook_url' => $s->webhookUrl(),
            'display_phone_number' => $s->display_phone_number,
            'verified_name' => $s->verified_name,
            'quality_rating' => $s->quality_rating,
            'last_tested_at' => optional($s->last_tested_at)->toDateTimeString(),
            'last_webhook_at' => optional($s->last_webhook_at)->toDateTimeString(),
            'automations' => $s->automationConfig(),
            'automation_definitions' => $defs,
            'placeholders' => [
                'sale' => ['{contact_name}', '{business_name}', '{invoice_number}', '{invoice_url}', '{total_amount}', '{paid_amount}', '{due_amount}'],
                'order' => ['{contact_name}', '{business_name}', '{order_number}', '{total_amount}', '{status}'],
            ],
            'stats' => $this->stats(),
        ];
    }

    protected function stats(): array
    {
        $base = WhatsAppMessage::where('direction', 'out');

        return [
            'sent' => (clone $base)->whereIn('status', ['sent', 'delivered', 'read'])->count(),
            'delivered' => (clone $base)->whereIn('status', ['delivered', 'read'])->count(),
            'failed' => (clone $base)->where('status', 'failed')->count(),
            'queued' => (clone $base)->where('status', 'queued')->count(),
            'received' => WhatsAppMessage::where('direction', 'in')->count(),
        ];
    }

    public function update(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'update', WhatsAppSetting::class);

        $data = $request->validate([
            'enabled' => 'required|boolean',
            'phone_number_id' => 'nullable|string|max:64',
            'business_account_id' => 'nullable|string|max:64',
            'access_token' => 'nullable|string|max:4000',
            'app_secret' => 'nullable|string|max:191',
            'api_version' => 'nullable|string|max:16|regex:/^v?\d+\.\d+$/',
            'default_country_code' => 'nullable|string|max:8',
            'automations' => 'nullable|array',
            'automations.*.enabled' => 'nullable|boolean',
            'automations.*.template' => 'nullable|string|max:2000',
        ], [
            'api_version.regex' => 'API version must look like v21.0',
        ]);

        $s = WhatsAppSetting::current();

        $s->enabled = (bool) $data['enabled'];
        foreach (['phone_number_id', 'business_account_id', 'api_version', 'default_country_code'] as $field) {
            if ($request->exists($field)) {
                $value = trim((string) ($data[$field] ?? ''));
                if ($field === 'phone_number_id' || $field === 'business_account_id') {
                    $value = preg_replace('/\D+/', '', $value); // ids are numeric; strip pasted spaces
                }
                if ($field === 'default_country_code') {
                    $value = preg_replace('/\D+/', '', $value);
                }
                if ($field === 'api_version' && $value !== '' && $value[0] !== 'v') {
                    $value = 'v'.$value;
                }
                $s->{$field} = $value !== '' ? $value : ($field === 'api_version' ? 'v21.0' : null);
            }
        }

        // Write-only secrets: replace only when the request carries a value.
        if ($request->exists('access_token') && trim((string) $data['access_token']) !== '') {
            $s->access_token = trim($data['access_token']);
            // A new token invalidates the cached test result.
            $s->last_tested_at = null;
        }
        if ($request->exists('app_secret') && trim((string) $data['app_secret']) !== '') {
            $s->app_secret = trim($data['app_secret']);
        }

        if ($request->exists('automations')) {
            $clean = [];
            foreach (WhatsAppSetting::AUTOMATIONS as $key => $def) {
                $row = $data['automations'][$key] ?? [];
                $clean[$key] = [
                    'enabled' => (bool) ($row['enabled'] ?? false),
                    'template' => trim((string) ($row['template'] ?? '')) ?: $def['template'],
                ];
            }
            $s->automations = $clean;
        }

        if ($s->enabled && ! $s->isConfigured()) {
            return response()->json([
                'message' => 'Enter the Phone Number ID and the access token before enabling WhatsApp.',
            ], 422);
        }

        $s->save();

        return response()->json(['success' => true] + $this->payload($s));
    }

    /** Clear the stored token / secret (so the admin can start over). */
    public function clearSecret(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'update', WhatsAppSetting::class);

        $field = $request->input('field');
        if (! in_array($field, ['access_token', 'app_secret'], true)) {
            return response()->json(['message' => 'Unknown field'], 422);
        }

        $s = WhatsAppSetting::current();
        $s->{$field} = null;
        if ($field === 'access_token') {
            $s->enabled = false;
            $s->last_tested_at = null;
        }
        $s->save();

        return response()->json(['success' => true] + $this->payload($s));
    }

    public function regenerateVerifyToken(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'update', WhatsAppSetting::class);

        $s = WhatsAppSetting::current();
        $s->verify_token = WhatsAppSetting::makeVerifyToken();
        $s->save();

        return response()->json(['success' => true, 'verify_token' => $s->verify_token]);
    }

    /**
     * Test Connection: calls the Graph API with the saved credentials and
     * reports what Meta says about the phone number. Also verifies the
     * WABA id when one is entered.
     */
    public function testConnection(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'update', WhatsAppSetting::class);

        $s = WhatsAppSetting::current();
        if (! $s->isConfigured()) {
            return response()->json([
                'ok' => false,
                'error' => 'Save the Phone Number ID and the access token first.',
            ], 422);
        }

        $client = CloudApiClient::make($s);
        $phone = $client->phoneNumberInfo();
        if (! $phone['ok']) {
            return response()->json([
                'ok' => false,
                'step' => 'phone_number',
                'error' => $phone['error'],
                'hint' => $phone['hint'] ?? null,
                'code' => $phone['code'] ?? null,
                'meta_message' => $phone['meta_message'] ?? null,
            ], 422);
        }

        $waba = $client->businessAccountInfo();

        $s->display_phone_number = $phone['data']['display_phone_number'] ?? null;
        $s->verified_name = $phone['data']['verified_name'] ?? null;
        $s->quality_rating = $phone['data']['quality_rating'] ?? null;
        $s->last_tested_at = now();
        $s->save();

        return response()->json([
            'ok' => true,
            'phone' => $phone['data'],
            'business_account' => $waba['ok'] ? ($waba['data'] ?: null) : null,
            'business_account_error' => $waba['ok'] ? null : ($waba['error'].' '.($waba['hint'] ?? '')),
            'webhook_seen' => (bool) $s->last_webhook_at,
            'last_webhook_at' => optional($s->last_webhook_at)->toDateTimeString(),
        ]);
    }

    /** Send a real text message to a number typed by the admin. */
    public function sendTest(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'update', WhatsAppSetting::class);

        $data = $request->validate([
            'phone' => 'required|string|max:32',
            'message' => 'nullable|string|max:1000',
        ]);

        $s = WhatsAppSetting::current();
        if (! $s->isConfigured()) {
            return response()->json(['ok' => false, 'error' => 'Save the Phone Number ID and the access token first.'], 422);
        }

        $phone = WhatsAppService::normalizePhone($data['phone'], $s);
        if (! $phone) {
            return response()->json(['ok' => false, 'error' => 'Enter the number in international format, e.g. +923001234567.'], 422);
        }

        $business = \App\Models\Setting::whereNull('deleted_at')->value('CompanyName') ?: 'Stocky';
        $body = trim((string) ($data['message'] ?? '')) ?: "\u{2705} Test message from {$business} — your WhatsApp integration works.";

        $log = WhatsAppService::sendNow($phone, $body, [
            'event' => 'test',
            'recipient_name' => $request->user('api')->username ?? null,
        ]);

        if ($log->status === WhatsAppMessage::STATUS_SENT) {
            return response()->json(['ok' => true, 'message_id' => $log->wa_message_id, 'log_id' => $log->id]);
        }

        return response()->json([
            'ok' => false,
            'error' => $log->error_message,
            'code' => $log->error_code,
            'log_id' => $log->id,
        ], 422);
    }

    // ------------------------------------------------------------------
    // Delivery log
    // ------------------------------------------------------------------

    public function messages(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', WhatsAppSetting::class);

        $perPage = (int) $request->input('limit', 25);
        $perPage = $perPage > 0 ? min($perPage, 200) : 25;

        $q = WhatsAppMessage::query()->orderByDesc('id');

        if ($request->filled('status')) {
            $q->where('status', $request->input('status'));
        }
        if ($request->filled('direction')) {
            $q->where('direction', $request->input('direction'));
        }
        if ($request->filled('event')) {
            $q->where('event', $request->input('event'));
        }
        if ($request->filled('search')) {
            $term = '%'.$request->input('search').'%';
            $q->where(function ($w) use ($term) {
                $w->where('phone', 'like', $term)
                    ->orWhere('recipient_name', 'like', $term)
                    ->orWhere('body', 'like', $term)
                    ->orWhere('error_message', 'like', $term);
            });
        }

        $page = $q->paginate($perPage);

        return response()->json([
            'messages' => $page->items(),
            'totalRows' => $page->total(),
            'stats' => $this->stats(),
        ]);
    }

    public function messageShow(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'view', WhatsAppSetting::class);

        return response()->json(['message' => WhatsAppMessage::findOrFail($id)]);
    }

    /** Re-send a failed outbound message synchronously. */
    public function retry(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'update', WhatsAppSetting::class);

        $log = WhatsAppMessage::where('direction', 'out')->findOrFail($id);
        if (! in_array($log->status, [WhatsAppMessage::STATUS_FAILED, WhatsAppMessage::STATUS_QUEUED], true)) {
            return response()->json(['ok' => false, 'error' => 'Only failed or queued messages can be retried.'], 422);
        }

        $ok = WhatsAppService::deliver($log);

        return response()->json([
            'ok' => $ok,
            'error' => $ok ? null : $log->error_message,
            'message' => $log->fresh(),
        ], $ok ? 200 : 422);
    }

    public function clearMessages(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'update', WhatsAppSetting::class);

        $q = WhatsAppMessage::query();
        if ($request->input('only') === 'failed') {
            $q->where('status', WhatsAppMessage::STATUS_FAILED);
        }
        $deleted = $q->delete();

        return response()->json(['success' => true, 'deleted' => $deleted]);
    }

    // ------------------------------------------------------------------
    // Used by other pages (Sales / Quotations "WhatsApp" action)
    // ------------------------------------------------------------------

    /** Lightweight flag any signed-in user may read. */
    public function status()
    {
        return response()->json(['active' => WhatsAppService::isActive()]);
    }

    /**
     * Send a composed message through the API. The caller already built
     * {phone, message} via sales_send_whatsapp / quotation_send_whatsapp, so
     * no extra permission beyond being signed in is required here.
     */
    public function send(Request $request)
    {
        $data = $request->validate([
            'phone' => 'required|string|max:32',
            'message' => 'required|string|max:4000',
            'recipient_name' => 'nullable|string|max:191',
            'reference_type' => 'nullable|string|max:64',
            'reference_id' => 'nullable|integer',
        ]);

        if (! WhatsAppService::isActive()) {
            return response()->json(['ok' => false, 'error' => 'WhatsApp integration is not enabled.'], 422);
        }

        $log = WhatsAppService::sendNow($data['phone'], $data['message'], [
            'event' => 'manual',
            'recipient_name' => $data['recipient_name'] ?? null,
            'reference_type' => $data['reference_type'] ?? null,
            'reference_id' => $data['reference_id'] ?? null,
        ]);

        if ($log->status === WhatsAppMessage::STATUS_SENT) {
            return response()->json(['ok' => true, 'log_id' => $log->id]);
        }

        return response()->json(['ok' => false, 'error' => $log->error_message, 'log_id' => $log->id], 422);
    }
}
