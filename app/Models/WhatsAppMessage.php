<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One row per WhatsApp message sent through the Cloud API (direction=out)
 * or received on the webhook (direction=in). Status callbacks from Meta
 * update the matching outbound row by wa_message_id.
 */
class WhatsAppMessage extends Model
{
    protected $table = 'whatsapp_messages';

    public const STATUS_QUEUED = 'queued';
    public const STATUS_SENT = 'sent';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_READ = 'read';
    public const STATUS_FAILED = 'failed';
    public const STATUS_RECEIVED = 'received';

    protected $fillable = [
        'direction', 'wa_message_id', 'phone', 'recipient_name', 'event', 'reference_type',
        'reference_id', 'body', 'status', 'error_code', 'error_message', 'response',
        'sent_at', 'delivered_at', 'read_at',
    ];

    protected $casts = [
        'response' => 'array',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
    ];
}
