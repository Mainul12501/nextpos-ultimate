<?php

namespace App\Jobs\WhatsApp;

use App\Models\WhatsAppMessage;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;

/**
 * Delivers one queued WhatsApp delivery-log row through the Cloud API.
 * Retries only on transport/rate-limit errors; permanent rejections
 * (bad number, outside 24h window, bad token) are recorded on the row and
 * not retried so the log stays readable.
 */
class SendWhatsAppMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** Must exceed the HTTP timeout (20s). */
    public int $timeout = 45;

    public function __construct(public int $messageId)
    {
    }

    /** Backoff schedule (seconds): 1m, 5m. */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(): void
    {
        $log = WhatsAppMessage::find($this->messageId);
        if (! $log || $log->status !== WhatsAppMessage::STATUS_QUEUED) {
            return; // deleted, or already handled by a retry/manual send
        }

        if (WhatsAppService::deliver($log)) {
            return;
        }

        $retryable = in_array((string) $log->error_code, ['network', '130429', '80007', '4', '2', '131000'], true);
        if ($retryable && $this->attempts() < $this->tries) {
            // Put it back to queued so the next attempt is allowed to send.
            $log->status = WhatsAppMessage::STATUS_QUEUED;
            $log->save();
            throw new RuntimeException('WhatsApp send failed (will retry): '.$log->error_message);
        }
    }
}
