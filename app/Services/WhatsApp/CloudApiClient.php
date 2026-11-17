<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsAppSetting;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Thin HTTP client for the Meta WhatsApp Business Cloud API
 * (https://graph.facebook.com/{version}/...). Every call returns a plain
 * ['ok' => bool, ...] array and never throws, so callers can surface a
 * readable error message to the admin instead of a stack trace.
 */
class CloudApiClient
{
    public function __construct(protected WhatsAppSetting $settings)
    {
    }

    public static function make(?WhatsAppSetting $settings = null): self
    {
        return new self($settings ?: WhatsAppSetting::current());
    }

    protected function base(): string
    {
        $version = trim((string) ($this->settings->api_version ?: 'v21.0'));
        if ($version !== '' && $version[0] !== 'v') {
            $version = 'v'.$version;
        }

        return 'https://graph.facebook.com/'.$version;
    }

    protected function http()
    {
        return Http::withToken((string) $this->settings->access_token)
            ->acceptJson()
            ->timeout(20)
            ->connectTimeout(10);
    }

    /**
     * GET /{phone_number_id} — verifies the token + phone number id pair
     * and returns the display number / verified name / quality rating.
     */
    public function phoneNumberInfo(): array
    {
        try {
            $res = $this->http()->get($this->base().'/'.$this->settings->phone_number_id, [
                'fields' => 'verified_name,display_phone_number,quality_rating,code_verification_status',
            ]);
        } catch (Throwable $e) {
            return $this->transportError($e);
        }

        if (! $res->successful()) {
            return $this->apiError($res);
        }

        return ['ok' => true, 'data' => $res->json() ?: []];
    }

    /**
     * GET /{business_account_id}?fields=name — optional; only used when a
     * WABA id was entered so we can confirm it belongs to the token.
     */
    public function businessAccountInfo(): array
    {
        if (empty($this->settings->business_account_id)) {
            return ['ok' => true, 'data' => []];
        }

        try {
            $res = $this->http()->get($this->base().'/'.$this->settings->business_account_id, [
                'fields' => 'name,timezone_id,message_template_namespace',
            ]);
        } catch (Throwable $e) {
            return $this->transportError($e);
        }

        if (! $res->successful()) {
            return $this->apiError($res);
        }

        return ['ok' => true, 'data' => $res->json() ?: []];
    }

    /** POST /{phone_number_id}/messages with a plain text body. */
    public function sendText(string $to, string $body, bool $previewUrl = true): array
    {
        return $this->sendPayload([
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'text',
            'text' => ['preview_url' => $previewUrl, 'body' => $body],
        ]);
    }

    /**
     * POST /{phone_number_id}/messages with an approved template. Used for
     * the "hello_world" smoke test and for opening a conversation outside
     * the 24-hour customer-service window.
     */
    public function sendTemplate(string $to, string $name, string $language = 'en_US', array $components = []): array
    {
        $template = ['name' => $name, 'language' => ['code' => $language]];
        if ($components) {
            $template['components'] = $components;
        }

        return $this->sendPayload([
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'template',
            'template' => $template,
        ]);
    }

    protected function sendPayload(array $payload): array
    {
        try {
            $res = $this->http()->post($this->base().'/'.$this->settings->phone_number_id.'/messages', $payload);
        } catch (Throwable $e) {
            return $this->transportError($e);
        }

        if (! $res->successful()) {
            return $this->apiError($res);
        }

        $json = $res->json() ?: [];

        return [
            'ok' => true,
            'message_id' => $json['messages'][0]['id'] ?? null,
            'data' => $json,
        ];
    }

    // ------------------------------------------------------------------
    // Error mapping
    // ------------------------------------------------------------------

    protected function transportError(Throwable $e): array
    {
        return [
            'ok' => false,
            'code' => 'network',
            'error' => 'Could not reach graph.facebook.com: '.$e->getMessage(),
            'hint' => 'Check that the server has outbound internet access (port 443) and that no firewall blocks graph.facebook.com.',
        ];
    }

    protected function apiError(Response $res): array
    {
        $json = $res->json() ?: [];
        $err = $json['error'] ?? [];
        $code = (string) ($err['code'] ?? $res->status());
        $sub = isset($err['error_subcode']) ? (string) $err['error_subcode'] : null;
        $metaMessage = (string) ($err['message'] ?? $res->body());
        $detail = (string) ($err['error_data']['details'] ?? '');

        [$friendly, $hint] = self::explain($code, $sub, $metaMessage, $detail);

        return [
            'ok' => false,
            'status' => $res->status(),
            'code' => $sub ? $code.'/'.$sub : $code,
            'error' => $friendly,
            'hint' => $hint,
            'meta_message' => $metaMessage.($detail ? ' — '.$detail : ''),
            'raw' => $json,
        ];
    }

    /**
     * Translate Meta error codes into something an admin can act on.
     * https://developers.facebook.com/docs/whatsapp/cloud-api/support/error-codes
     */
    public static function explain(string $code, ?string $sub, string $metaMessage, string $detail = ''): array
    {
        $map = [
            '190' => ['The access token is invalid or has expired.', 'Generate a permanent token from a System User in Meta Business Settings (with whatsapp_business_messaging + whatsapp_business_management permissions) and paste it again.'],
            '0' => ['The access token is invalid or has expired.', 'Generate a permanent System User token and paste it again.'],
            '10' => ['The token does not have permission for this action.', 'Add the whatsapp_business_messaging and whatsapp_business_management permissions to the System User token, and make sure the app is added to the WhatsApp Business Account.'],
            '200' => ['The token does not have permission for this action.', 'Add the whatsapp_business_messaging permission and assign the WhatsApp account to the System User.'],
            '100' => ['Meta rejected a parameter (usually a wrong Phone Number ID or an invalid phone number).', 'Double-check the Phone Number ID (it is the numeric id from WhatsApp → API Setup, NOT the phone number itself) and that the recipient number includes a country code.'],
            '803' => ['The Phone Number ID or Business Account ID does not exist or is not visible to this token.', 'Copy the numeric ids from Meta → WhatsApp → API Setup and make sure the System User has access to this WhatsApp account.'],
            '33' => ['The Phone Number ID does not exist or is not accessible with this token.', 'Copy the numeric Phone Number ID from Meta → WhatsApp → API Setup.'],
            '130429' => ['Rate limit hit: too many messages sent in a short time.', 'Wait a minute and retry. Meta throttles per phone number.'],
            '131030' => ['The recipient is not in your allowed list (test numbers).', 'While the app is in development mode only numbers added under WhatsApp → API Setup → "To" can receive messages. Add the number there, or use a production phone number.'],
            '131047' => ['More than 24 hours have passed since the customer last messaged you, so a free-form text is not allowed.', 'Outside the 24-hour window Meta only accepts approved message templates. Ask the customer to message you first, or create an approved template.'],
            '131026' => ['Message undeliverable: the number is not a WhatsApp user or has blocked business messages.', 'Check the number (with country code) and that it has WhatsApp installed.'],
            '131051' => ['Unsupported message type.', 'Only text and template messages are sent from Stocky.'],
            '131000' => ['Meta reported a generic failure.', 'Retry in a moment. If it persists check the WhatsApp Manager for alerts on the phone number.'],
            '131009' => ['A parameter value is not valid.', $detail ?: $metaMessage],
            '131031' => ['The WhatsApp Business Account has been restricted or disabled.', 'Open WhatsApp Manager → Account tools to see the policy violation and appeal.'],
            '133010' => ['The phone number is not registered on the Cloud API.', 'In Meta → WhatsApp → API Setup register the phone number (Two-step verification PIN) before sending.'],
            '133005' => ['The two-step verification PIN is wrong.', 'Reset the PIN in WhatsApp Manager and register the number again.'],
            '368' => ['The WhatsApp account is temporarily blocked for policy violations.', 'Check WhatsApp Manager for details.'],
            '80007' => ['Throughput limit reached for this phone number.', 'Wait and retry; raise the messaging tier by keeping the quality rating high.'],
            '4' => ['The app has hit its API call limit.', 'Wait a few minutes and retry.'],
            '2' => ['Meta is temporarily unavailable.', 'Retry in a few minutes.'],
        ];

        if (isset($map[$code])) {
            return $map[$code];
        }

        return ['Meta returned error '.$code.': '.$metaMessage.($detail ? ' ('.$detail.')' : ''), 'See the raw response in the delivery log for details.'];
    }
}
