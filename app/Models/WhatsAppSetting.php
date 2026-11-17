<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Singleton settings row for the WhatsApp Business (Meta Cloud API)
 * integration. Secrets (access token / app secret) are write-only from the
 * admin UI: the controller never echoes them back in full.
 */
class WhatsAppSetting extends Model
{
    protected $table = 'whatsapp_settings';

    protected $fillable = [
        'enabled', 'phone_number_id', 'business_account_id', 'access_token', 'app_secret',
        'verify_token', 'api_version', 'default_country_code', 'display_phone_number',
        'verified_name', 'quality_rating', 'last_tested_at', 'last_webhook_at', 'automations',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'automations' => 'array',
        'last_tested_at' => 'datetime',
        'last_webhook_at' => 'datetime',
    ];

    /** Automation keys shown on the settings page, with default templates. */
    public const AUTOMATIONS = [
        'sale_created' => [
            'label' => 'Invoice created (sale)',
            'hint' => 'Sent to the customer when a sale / invoice is created (POS and back office).',
            'template' => "Hello {contact_name}, thank you for your purchase at {business_name}.\nInvoice {invoice_number}\nTotal: {total_amount}\nPaid: {paid_amount}\nDue: {due_amount}\nView your invoice: {invoice_url}",
        ],
        'payment_created' => [
            'label' => 'Payment received',
            'hint' => 'Sent when a payment is recorded against a sale.',
            'template' => "Hello {contact_name}, we received your payment of {paid_amount} for invoice {invoice_number} at {business_name}.\nRemaining due: {due_amount}\nThank you!",
        ],
        'order_confirmed' => [
            'label' => 'Online order confirmed',
            'hint' => 'Sent when an online store order is confirmed.',
            'template' => "Hello {contact_name}, your order {order_number} at {business_name} has been confirmed.\nTotal: {total_amount}\nWe will let you know when it ships.",
        ],
        'order_shipped' => [
            'label' => 'Online order shipped',
            'hint' => 'Sent when an online store order is marked as shipped.',
            'template' => "Hello {contact_name}, good news! Your order {order_number} from {business_name} has been shipped.",
        ],
        'order_delivered' => [
            'label' => 'Online order delivered',
            'hint' => 'Sent when an online store order is marked as delivered.',
            'template' => "Hello {contact_name}, your order {order_number} from {business_name} has been delivered. Thank you for shopping with us!",
        ],
    ];

    public static function current(): self
    {
        $row = static::query()->firstOrCreate([]);
        if (empty($row->verify_token)) {
            $row->verify_token = static::makeVerifyToken();
            $row->save();
        }

        return $row;
    }

    public static function makeVerifyToken(): string
    {
        return Str::random(32);
    }

    public function isConfigured(): bool
    {
        return ! empty($this->phone_number_id) && ! empty($this->access_token);
    }

    /** Ready to actually send: enabled + credentials present. */
    public function isActive(): bool
    {
        return $this->enabled && $this->isConfigured();
    }

    /** Merge stored automation config over defaults. */
    public function automationConfig(): array
    {
        $stored = $this->automations ?: [];
        $out = [];
        foreach (self::AUTOMATIONS as $key => $def) {
            $row = is_array($stored[$key] ?? null) ? $stored[$key] : [];
            $out[$key] = [
                'enabled' => (bool) ($row['enabled'] ?? false),
                'template' => (string) (($row['template'] ?? '') !== '' ? $row['template'] : $def['template']),
            ];
        }

        return $out;
    }

    public function automationEnabled(string $key): bool
    {
        return (bool) ($this->automationConfig()[$key]['enabled'] ?? false);
    }

    public function automationTemplate(string $key): string
    {
        return (string) ($this->automationConfig()[$key]['template'] ?? '');
    }

    public function webhookUrl(): string
    {
        return url('/api/whatsapp/webhook');
    }
}
