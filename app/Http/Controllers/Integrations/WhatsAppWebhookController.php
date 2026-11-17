<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use App\Models\WhatsAppSetting;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Public callback URL that is pasted into Meta → WhatsApp → Configuration.
 *
 *  GET  /api/whatsapp/webhook  — Meta's one-time verification handshake
 *                                (hub.mode / hub.verify_token / hub.challenge).
 *  POST /api/whatsapp/webhook  — delivery statuses + inbound messages, signed
 *                                with X-Hub-Signature-256 (HMAC of the raw
 *                                body with the app secret).
 *
 * Always answers 200 on POST once the signature is accepted, otherwise Meta
 * keeps retrying and eventually disables the subscription.
 */
class WhatsAppWebhookController extends Controller
{
    public function verify(Request $request)
    {
        $mode = $request->query('hub_mode', $request->query('hub.mode'));
        $token = (string) $request->query('hub_verify_token', $request->query('hub.verify_token'));
        $challenge = $request->query('hub_challenge', $request->query('hub.challenge'));

        $settings = WhatsAppService::settings();
        $expected = (string) ($settings->verify_token ?? '');

        if ($mode === 'subscribe' && $expected !== '' && hash_equals($expected, $token)) {
            try {
                $settings->last_webhook_at = now();
                $settings->save();
            } catch (Throwable $e) {
                // ignore
            }

            return response((string) $challenge, 200)->header('Content-Type', 'text/plain');
        }

        if ($expected === '') {
            return response('WhatsApp integration is not configured on this Stocky install.', 403);
        }

        return response('Verify token mismatch. Copy the Webhook Verify Token from Stocky → Integrations → WhatsApp.', 403);
    }

    public function handle(Request $request)
    {
        $settings = WhatsAppService::settings();
        if (! $settings) {
            return response()->json(['ok' => false, 'error' => 'not configured'], 404);
        }

        $raw = (string) $request->getContent();

        if (! empty($settings->app_secret)) {
            $header = (string) $request->header('X-Hub-Signature-256', '');
            $expected = 'sha256='.hash_hmac('sha256', $raw, $settings->app_secret);
            if ($header === '' || ! hash_equals($expected, $header)) {
                Log::warning('[WhatsApp] webhook rejected: bad X-Hub-Signature-256');

                return response()->json(['ok' => false, 'error' => 'invalid signature'], 401);
            }
        }

        $body = json_decode($raw, true);
        if (! is_array($body)) {
            return response()->json(['ok' => true, 'ignored' => 'empty body']);
        }

        try {
            $counts = WhatsAppService::processWebhook($body);
        } catch (Throwable $e) {
            report($e);
            $counts = ['error' => $e->getMessage()];
        }

        return response()->json(['ok' => true] + $counts);
    }
}
