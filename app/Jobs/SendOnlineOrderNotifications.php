<?php

namespace App\Jobs;

use App\Http\Controllers\Api\Store\CheckoutController;
use App\Models\OnlineOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Customer confirmation email + store-owner email + admin bell/push for an
 * online order that has just been placed (or just been paid).
 *
 * This runs on the queue so the checkout response never waits on the mail
 * server. Both emails used to be sent inline from the request: with an
 * unreachable/slow SMTP host each connect blocked for the socket timeout,
 * the web server gave up with a 504 HTML page, and the shopper saw an
 * "Unexpected token '<'" alert while the order row was already committed.
 * The per-minute `queue:work --once` in Console\Kernel drains this job.
 */
class SendOnlineOrderNotifications implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public function __construct(public int $orderId) {}

    public function handle(CheckoutController $checkout): void
    {
        $order = OnlineOrder::find($this->orderId);
        if (! $order) {
            return;
        }

        // Both are best-effort and log their own failures.
        $checkout->sendOrderEmail($order);
        $checkout->notifyAdminsOfOrder($order);
    }
}
