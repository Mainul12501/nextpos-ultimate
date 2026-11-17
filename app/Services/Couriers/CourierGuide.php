<?php

namespace App\Services\Couriers;

/**
 * Setup notes for Couriers → Guide: how each courier's status updates reach
 * Stocky, and the gotchas worth knowing before the first booking. The keys a
 * courier needs come from its driver meta (credential labels and help).
 *
 * Webhook modes:
 *   panel   — paste the account's webhook URL in the courier's dashboard
 *   request — the courier registers it for you (send them the URL)
 *   auto    — Stocky registers it with every parcel; nothing to do
 *   paid    — needs a paid add-on from the courier
 *   none    — the courier cannot push; scheduled status checks keep it current
 */
final class CourierGuide
{
    private const WEBHOOKS = [
        'aftership' => ['panel', 'AfterShip → Settings → Webhooks: add the webhook URL, then copy the webhook secret into the account.'],
        '17track' => ['panel', 'In the 17TRACK API dashboard, set the webhook URL. Your security key also signs the updates.'],
        'leopards' => ['request', 'Send the webhook URL to Leopards and ask them to register it for the Push API.'],
        'trax' => ['panel', 'Enable webhooks in the Trax portal and paste the webhook URL.'],
        'easypost' => ['panel', 'EasyPost → Webhooks: add the webhook URL with a secret, and paste the same secret on the account.'],
        'shippo' => ['panel', 'Shippo → webhooks: add the webhook URL for tracking updates.'],
        'shiprocket' => ['panel', 'Shiprocket → Settings → API → Webhooks: paste the webhook URL and a security token; put the same token on the account.'],
        'sendcloud' => ['panel', 'In your Sendcloud API integration, set the webhook URL. The secret key verifies the updates.'],
        'oto' => ['panel', 'Add the webhook URL in OTO and copy its secret key into the account.'],
        'torod' => ['panel', 'Set the webhook URL in the Torod merchant panel.'],
        'easyparcel' => ['panel', 'Set the webhook URL in your EasyParcel integration settings.'],
        'biteship' => ['panel', 'Biteship dashboard → webhooks: paste the URL and set a header name and value; copy both into the account.'],
        'shiplogic' => ['panel', 'Set the webhook URL in your Shiplogic portal.'],
        'sendbox' => ['auto', 'Registered with every shipment — nothing to set up.'],
        'quiqup' => ['request', 'Give the webhook URL to Quiqup; they return the signing token for the account.'],
        'smsa' => ['request', 'Ask SMSA to enable status pushes to the webhook URL.'],
        'jt_express' => ['panel', 'Set the push address to the webhook URL on the J&T open platform.'],
        'bosta' => ['auto', 'Registered with every delivery — nothing to set up.'],
        'steadfast' => ['panel', 'Steadfast panel → API: set the webhook URL as the Callback URL with an auth token, and copy the token into the account.'],
        'pathao' => ['panel', 'Pathao merchant panel → Developer API: set the webhook URL as the Callback URL with a secret, and copy the secret into the account.'],
        'redx' => ['panel', 'RedX merchant panel → Developer API → Webhook: paste the webhook URL.'],
        'delhivery' => ['request', 'Delhivery sets up Scan Push on request: send the webhook URL to lastmile-integration@delhivery.com.'],
        'shadowfax' => ['request', 'Register the webhook URL for status callbacks in the SFX 360 partner portal, or through your account manager.'],
        'ninjavan' => ['panel', 'Ninja Dashboard → webhooks: paste the webhook URL. Required — Ninja Van has no status check.'],
        'flash_express' => ['request', 'Ask Flash Express support to send status pushes to the webhook URL.'],
        'lalamove' => ['panel', 'Lalamove Partner Portal → Developers: set the webhook URL.'],
        'fedex' => ['paid', 'Needs FedEx Advanced Integrated Visibility (paid, US/Canada services): create the webhook in the FedEx developer portal with the webhook URL and a security token, and paste the token on the account.'],
        'ups' => ['auto', 'Turn on "Push updates with UPS Track Alert" on the account: each parcel is then subscribed with the webhook URL (UPS bills per tracking number).'],
    ];

    private const TIPS = [
        'manual' => ['Set the tracking link so staff and customers can open the courier\'s page.', 'Move the status by hand from Courier Shipments → Update status.'],
        'aftership' => ['Book with any courier and type its tracking number; a default courier slug skips detection.'],
        '17track' => ['Book with any courier and type its tracking number.'],
        'postex' => ['"Test connection" lists your pickup address codes.'],
        'tcs' => ['"Test connection" lists your cost centers.'],
        'trax' => ['Trax needs an email on every order: set "Email when the customer has none".', '"Test connection" lists your pickup address IDs.'],
        'mnp' => ['"Test connection" lists your pickup location IDs.'],
        'callcourier' => ['Stocky prints the label (Call Courier has no label API).'],
        'daewoo' => ['Stocky prints the label (Daewoo has no label API).'],
        'blueex' => ['Book in the BlueEx portal, then type the CN here; Stocky keeps the status current.'],
        'shiprocket' => ['Use a dedicated API user, not your main Shiprocket login.'],
        'easyparcel' => ['Bookings are paid from your EasyParcel wallet; cancel in the EasyParcel portal.'],
        'sendbox' => ['Bookings are paid from your Sendbox wallet; cancel in the Sendbox dashboard.'],
        'aramex' => ['There is no cancel through the API: cancel in the Aramex portal.'],
        'jt_express' => ['The receiver\'s state is required: fill it in the booking form.'],
        'bosta' => ['Use a Full Access API key so bookings can be cancelled.'],
        'mylerz' => ['"Test connection" lists your Mylerz warehouses.'],
        'steadfast' => ['Several wrong API keys lock API access for an hour — paste them carefully.'],
        'pathao' => ['"Test connection" lists your store IDs.'],
        'ecourier' => ['"Test connection" lists your package codes.'],
        'delhivery' => ['The pickup location must match your warehouse name in Delhivery One exactly — case and spaces included.'],
        'ninjavan' => ['Waybill printing needs Ninja Van to enable access for your account.'],
        'lalamove' => ['Set the pickup latitude and longitude on the account; each order needs drop-off coordinates.'],
        'dhl_express' => ['The label is given once, at booking, and kept in Stocky for reprints.', 'In test mode only DHL\'s sample waybills can be tracked.'],
        'fedex' => ['FedEx keeps the Track API in its own project: add that key and secret if you created one.'],
        'ups' => ['Labels are images laid out for 4 × 6 paper.', 'COD works from US, Puerto Rico and Canada origins at package level, shipment level elsewhere.'],
    ];

    /** @return array{mode: string, text: string} */
    public static function webhook(string $code, array $capabilities): array
    {
        if (! ($capabilities['webhooks'] ?? false)) {
            return ['mode' => 'none', 'text' => 'This courier cannot push updates: Stocky checks the status on the schedule in Courier Settings.'];
        }
        [$mode, $text] = self::WEBHOOKS[$code] ?? ['panel', 'Paste the webhook URL in the courier\'s dashboard (webhook or callback settings).'];

        return ['mode' => $mode, 'text' => $text];
    }

    /** @return string[] */
    public static function tips(string $code): array
    {
        return self::TIPS[$code] ?? [];
    }
}
