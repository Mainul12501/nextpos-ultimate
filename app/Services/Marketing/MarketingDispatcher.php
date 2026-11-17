<?php

namespace App\Services\Marketing;

use App\Mail\MarketingCampaignEmail;
use App\Models\MarketingCampaign;
use App\Models\MarketingCampaignRecipient;
use App\Models\MarketingSetting;
use App\Models\Setting;
use Carbon\Carbon;
use GuzzleHttp\Client as HttpClient;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Builds the recipient list for a campaign and delivers it across the
 * configured channel (sms / email / whatsapp). Designed to be safe by default:
 * SMS/WhatsApp are only transmitted when the relevant provider is enabled and
 * configured in Marketing Settings; otherwise recipients are marked "skipped".
 */
class MarketingDispatcher
{
    protected $resolver;

    public function __construct(SegmentResolver $resolver = null)
    {
        $this->resolver = $resolver ?: new SegmentResolver;
    }

    /**
     * Resolve the campaign audience and (re)create its recipient rows.
     */
    public function buildRecipients(MarketingCampaign $campaign): int
    {
        // Clear any previous recipient rows (e.g. a re-send).
        MarketingCampaignRecipient::where('campaign_id', $campaign->id)->delete();

        $leads = false;
        if ($campaign->all_customers || ! $campaign->segment_id) {
            $clients = $this->resolver->resolve(true, []);
        } else {
            $segment = $campaign->segment;
            $clients = $segment
                ? $this->resolver->resolveSegment($segment)
                : $this->resolver->resolve(true, []);
            // A CRM lead segment: recipients are leads, not clients.
            $leads = $segment && $segment->audience === 'crm_leads';
        }

        $now = Carbon::now();
        $rows = [];
        foreach ($clients as $client) {
            $rows[] = [
                'campaign_id'   => $campaign->id,
                'client_id'     => $leads ? null : $client->id,
                'crm_lead_id'   => $leads ? $client->id : null,
                'name'          => $this->clientName($client),
                'phone'         => $client->phone,
                'email'         => $client->email,
                'status'        => 'pending',
                'error_message' => null,
                'sent_at'       => null,
                'delivered_at'  => null,
                'created_at'    => $now,
                'updated_at'    => $now,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            MarketingCampaignRecipient::insert($chunk);
        }

        $count = count($rows);
        $campaign->update([
            'total_recipients' => $count,
            'pending_count'    => $count,
            'sent_count'       => 0,
            'delivered_count'  => 0,
            'failed_count'     => 0,
        ]);

        return $count;
    }

    /**
     * Deliver a campaign to all of its pending recipients.
     */
    public function dispatchCampaign(MarketingCampaign $campaign): void
    {
        $campaign->update(['status' => 'sending']);

        // Build the recipient list when there is nothing left to send: either a
        // first send (no recipients yet) or a manual re-send (all prior
        // recipients already processed). buildRecipients() resets the list, so a
        // re-send re-resolves the audience and retries from scratch.
        $hasPending = MarketingCampaignRecipient::where('campaign_id', $campaign->id)
            ->where('status', 'pending')->exists();

        if (! $hasPending) {
            $this->buildRecipients($campaign);
        }

        $settings = MarketingSetting::current();

        if ($campaign->type === 'email') {
            $this->configureMail($settings);
        }

        $sent = 0;
        $failed = 0;

        MarketingCampaignRecipient::where('campaign_id', $campaign->id)
            ->where('status', 'pending')
            ->orderBy('id')
            ->chunkById(max(1, (int) $settings->batch_size), function ($recipients) use ($campaign, $settings, &$sent, &$failed) {
                foreach ($recipients as $recipient) {
                    $this->sendToRecipient($campaign, $recipient, $settings, $sent, $failed);
                }
            });

        $campaign->refresh();
        $delivered = MarketingCampaignRecipient::where('campaign_id', $campaign->id)
            ->whereIn('status', ['sent', 'delivered'])->count();
        $failedCount = MarketingCampaignRecipient::where('campaign_id', $campaign->id)
            ->where('status', 'failed')->count();
        $pending = MarketingCampaignRecipient::where('campaign_id', $campaign->id)
            ->whereIn('status', ['pending', 'skipped'])->count();

        $campaign->update([
            'status'          => $failedCount > 0 && $delivered === 0 ? 'failed' : 'sent',
            'sent_at'         => Carbon::now(),
            'sent_count'      => $delivered,
            'delivered_count' => $delivered,
            'failed_count'    => $failedCount,
            'pending_count'   => $pending,
        ]);
    }

    protected function sendToRecipient(MarketingCampaign $campaign, MarketingCampaignRecipient $recipient, MarketingSetting $settings, &$sent, &$failed): void
    {
        $message = $this->personalize($campaign->message_content, $recipient);

        // A lead may have unsubscribed after the list was built.
        if ($recipient->crm_lead_id && \App\Models\CrmLead::whereKey($recipient->crm_lead_id)->where('marketing_opt_out', true)->exists()) {
            $this->markSkipped($recipient, 'Unsubscribed from marketing');
            return;
        }

        try {
            if ($campaign->type === 'email') {
                if (! $settings->email_enabled) {
                    $this->markSkipped($recipient, 'Email channel disabled');
                    return;
                }
                if (empty($recipient->email)) {
                    $this->markSkipped($recipient, 'No email address');
                    return;
                }
                $this->sendEmail($campaign, $recipient, $message, $settings);
            } elseif ($campaign->type === 'sms') {
                if (! $settings->sms_enabled || empty($settings->sms_api_url)) {
                    $this->markSkipped($recipient, 'SMS gateway not configured');
                    return;
                }
                if (empty($recipient->phone)) {
                    $this->markSkipped($recipient, 'No phone number');
                    return;
                }
                $this->sendHttp($settings, 'sms', $recipient->phone, $message);
            } elseif ($campaign->type === 'whatsapp') {
                if (empty($recipient->phone)) {
                    $this->markSkipped($recipient, 'No phone number');
                    return;
                }
                // WhatsApp campaigns are delivered through Integrations → WhatsApp
                // (Meta Cloud API). Each send is recorded in the WhatsApp delivery log.
                if (! \App\Services\WhatsApp\WhatsAppService::isActive()) {
                    $this->markSkipped($recipient, 'WhatsApp not configured (Integrations → WhatsApp)');
                    return;
                }
                $log = \App\Services\WhatsApp\WhatsAppService::sendNow($recipient->phone, $message, [
                    'event' => 'campaign',
                    'recipient_name' => $recipient->name ?? null,
                    'reference_type' => 'marketing_campaign',
                    'reference_id' => $campaign->id,
                ]);
                if ($log->status !== \App\Models\WhatsAppMessage::STATUS_SENT) {
                    throw new \RuntimeException($log->error_message ?: 'WhatsApp send failed');
                }
            }

            $recipient->update([
                'status'  => 'sent',
                'sent_at' => Carbon::now(),
            ]);
            $sent++;
            if ($recipient->crm_lead_id) {
                $this->logOnLead($campaign, $recipient);
            }
        } catch (\Throwable $e) {
            Log::error('Marketing send failed (campaign '.$campaign->id.', recipient '.$recipient->id.'): '.$e->getMessage());
            $recipient->update([
                'status'        => 'failed',
                'error_message' => substr($e->getMessage(), 0, 500),
            ]);
            $failed++;
        }
    }

    protected function sendEmail(MarketingCampaign $campaign, MarketingCampaignRecipient $recipient, string $message, MarketingSetting $settings): void
    {
        $appSettings = Setting::where('deleted_at', '=', null)->first();
        $businessName = $settings->default_sender_name
            ?: ($appSettings->CompanyName ?? config('app.name'));

        $attachment = null;
        if (! empty($campaign->attachment)) {
            $path = public_path($campaign->attachment);
            if (is_file($path)) {
                $attachment = $path;
            }
        }

        // Personalised again with HTML-escaped values (the template itself is staff-written HTML).
        $body = nl2br($this->personalize($campaign->message_content, $recipient, true));
        // Lead emails always carry an unsubscribe link (customers are handled by the shop's own rules).
        if ($recipient->crm_lead_id && ! str_contains((string) $campaign->message_content, '{unsubscribe_url}')) {
            $body .= '<br><br><small style="color:#94a3b8">'.e(__('Don\'t want these emails?')).' <a href="'
                .e(\App\Services\Crm\CrmLeadSegmentResolver::unsubscribeUrl((int) $recipient->crm_lead_id)).'">'.e(__('Unsubscribe')).'</a></small>';
        }

        Mail::to($recipient->email)->send(new MarketingCampaignEmail([
            'subject'      => $this->personalize($campaign->subject ?: $campaign->title, $recipient),
            'body'         => $body,
            'company_name' => $businessName,
            'attachment'   => $attachment,
        ]));
    }

    /**
     * Generic configurable HTTP send for the SMS gateway. (WhatsApp goes
     * through App\Services\WhatsApp\WhatsAppService — Meta Cloud API.)
     */
    protected function sendHttp(MarketingSetting $settings, string $channel, string $to, string $message): void
    {
        $url       = $settings->sms_api_url;
        $method    = strtoupper($settings->sms_http_method ?: 'POST');
        $apiKey    = $settings->sms_api_key;
        $toField   = $settings->sms_to_field ?: 'to';
        $msgField  = $settings->sms_message_field ?: 'message';
        $extra     = $settings->sms_extra_params ?: [];

        $payload = array_merge(is_array($extra) ? $extra : [], [
            $toField  => $to,
            $msgField => $message,
        ]);

        if ($channel === 'sms' && ! empty($settings->sms_sender_id)) {
            $payload['sender'] = $settings->sms_sender_id;
        }

        $headers = ['Accept' => 'application/json'];
        if (! empty($apiKey)) {
            $headers['Authorization'] = 'Bearer '.$apiKey;
        }

        $options = ['headers' => $headers, 'http_errors' => false];
        if ($method === 'GET') {
            $options['query'] = $payload;
        } else {
            $options['json'] = $payload;
        }

        $client = new HttpClient(['timeout' => 30]);
        $response = $client->request($method, $url, $options);
        $status = $response->getStatusCode();

        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException(ucfirst($channel).' gateway returned HTTP '.$status.': '.substr((string) $response->getBody(), 0, 300));
        }
    }

    /**
     * Replace personalization variables in a message body.
     */
    public function personalize(?string $text, MarketingCampaignRecipient $recipient, bool $html = false): string
    {
        if ($text === null) {
            return '';
        }

        $client = $recipient->client;
        $lastPurchase = '';
        $totalSpent = '';
        if ($client) {
            // last_purchase / total_spent are not stored on the recipient; pull live stats lazily.
            $stats = DB::table('sales')
                ->where('client_id', $client->id)
                ->whereNull('deleted_at')
                ->where('statut', 'completed')
                ->selectRaw('MAX(date) as last_purchase, COALESCE(SUM(GrandTotal),0) as total_spent')
                ->first();
            $lastPurchase = $stats->last_purchase ?? '';
            $totalSpent = $stats->total_spent ?? '';
        }

        $lead = $recipient->crm_lead_id ? $recipient->lead : null;

        $vars = [
            '{customer_name}' => $recipient->name ?: '',
            '{first_name}'    => $lead ? ($lead->first_name ?: explode(' ', (string) $lead->name)[0]) : (optional($client)->firstname ?: explode(' ', (string) $recipient->name)[0]),
            '{company}'       => $lead ? (string) $lead->company_name : '',
            '{phone}'         => $recipient->phone ?: '',
            '{email}'         => $recipient->email ?: '',
            '{last_purchase}' => (string) $lastPurchase,
            '{total_spent}'   => (string) $totalSpent,
            '{unsubscribe_url}' => $lead ? \App\Services\Crm\CrmLeadSegmentResolver::unsubscribeUrl($lead->id) : '',
        ];
        // In an HTML email the values (names typed into public forms…) must not become markup.
        if ($html) {
            $vars = array_map(fn ($v) => e((string) $v), $vars);
        }

        return strtr($text, $vars);
    }

    /** "Received campaign …" on the lead's history (best effort). */
    protected function logOnLead(MarketingCampaign $campaign, MarketingCampaignRecipient $recipient): void
    {
        try {
            \App\Models\CrmLeadEvent::create([
                'crm_lead_id' => $recipient->crm_lead_id,
                'type'        => 'campaign',
                'meta'        => ['campaign' => $campaign->title, 'campaign_id' => $campaign->id, 'channel' => $campaign->type],
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    protected function markSkipped(MarketingCampaignRecipient $recipient, string $reason): void
    {
        $recipient->update([
            'status'        => 'skipped',
            'error_message' => $reason,
        ]);
    }

    protected function clientName($client): string
    {
        $full = trim(($client->firstname ?? '').' '.($client->lastname ?? ''));

        return $full !== '' ? $full : ($client->name ?: '');
    }

    /**
     * Apply the SMTP credentials stored in the servers table (same source the
     * rest of Stocky uses for outgoing mail).
     */
    protected function configureMail(MarketingSetting $settings): void
    {
        $server = DB::table('servers')->where('deleted_at', '=', null)->first();
        $appSettings = DB::table('settings')->where('deleted_at', '=', null)->first();

        if (! $server || ! $appSettings) {
            return;
        }

        $fromEmail = $settings->email_from_address
            ?: (($server->sender_email ?? null) ?: $appSettings->email);
        $fromName = $settings->email_from_name
            ?: ($server->sender_name ?? config('app.name'));

        Config::set('mail', [
            'driver'     => $server->mail_mailer,
            'host'       => $server->host,
            'port'       => $server->port,
            'from'       => ['address' => $fromEmail, 'name' => $fromName],
            'encryption' => $server->encryption,
            'username'   => $server->username,
            'password'   => $server->password,
            'sendmail'   => '/usr/sbin/sendmail -bs',
            'pretend'    => false,
            'stream'     => [
                'ssl' => [
                    'allow_self_signed' => true,
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                ],
            ],
        ]);
    }
}
