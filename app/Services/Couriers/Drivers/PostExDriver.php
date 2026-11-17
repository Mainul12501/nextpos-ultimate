<?php

namespace App\Services\Couriers\Drivers;

use App\Models\Shipment;
use App\Services\Couriers\AbstractCourierDriver;
use App\Services\Couriers\CourierException;
use App\Services\Couriers\CourierStatus;
use App\Services\Couriers\Data\BookingResult;
use App\Services\Couriers\Data\ConnectionResult;
use App\Services\Couriers\Data\ShipmentRequest;
use App\Services\Couriers\Data\TrackingEvent;
use App\Services\Couriers\Data\TrackingResult;
use App\Services\Couriers\Support\Phone;
use App\Services\Couriers\Support\StatusText;
use Carbon\Carbon;
use Illuminate\Http\Client\Response;

/**
 * PostEx (Pakistan, COD). Built from the official "Merchant API Integration
 * Guide v4.1.9". Base https://api.postex.pk/services/integration/api/order/,
 * header `token`. Envelope {statusCode:"200", statusMessage, dist}. Cities
 * are names (operationalCityName); the airway bill is a PDF; COD settlement
 * comes from payment-status. No webhooks — status is polled. New orders stay
 * "Unbooked" until a load sheet is generated in the PostEx portal.
 */
class PostExDriver extends AbstractCourierDriver
{
    private const BASE = 'https://api.postex.pk/services/integration/api/order/';

    /** transactionStatus (lower-case) → Stocky status. */
    private const STATUSES = [
        'unbooked' => CourierStatus::BOOKED,
        'booked' => CourierStatus::BOOKED,
        'picked by postex' => CourierStatus::PICKED_UP,
        'en-route to postex warehouse' => CourierStatus::PICKED_UP,
        'postex warehouse' => CourierStatus::IN_TRANSIT,
        'out for delivery' => CourierStatus::OUT_FOR_DELIVERY,
        'attempted' => CourierStatus::FAILED_ATTEMPT,
        'delivery under review' => CourierStatus::FAILED_ATTEMPT,
        'delivered' => CourierStatus::DELIVERED,
        'out for return' => CourierStatus::RETURNING,
        'returned' => CourierStatus::RETURNED,
        'un-assigned by me' => CourierStatus::CANCELLED,
        'expired' => CourierStatus::CANCELLED,
    ];

    /** transactionStatusMessageCode → Stocky status (history lines). */
    private const HISTORY_CODES = [
        '0001' => CourierStatus::BOOKED,
        '0002' => CourierStatus::RETURNED,
        '0003' => CourierStatus::IN_TRANSIT,
        '0004' => CourierStatus::IN_TRANSIT,
        '0005' => CourierStatus::DELIVERED,
        '0006' => CourierStatus::RETURNED,
        '0007' => CourierStatus::RETURNED,
        '0008' => CourierStatus::FAILED_ATTEMPT,
        '0013' => CourierStatus::FAILED_ATTEMPT,
    ];

    public static function meta(): array
    {
        return [
            'code' => 'postex',
            'name' => 'PostEx',
            'region' => 'Pakistan',
            'countries' => ['PK'],
            'website' => 'https://postex.pk',
            'tracking_url' => null,
            'description' => 'COD deliveries across Pakistan. Orders, airway bills, tracking and COD settlement through the PostEx merchant API.',
            'capabilities' => [
                'booking' => true,
                'labels' => true,
                'cancel' => true,
                'tracking' => true,
                'cod' => true,
                'cities' => true,
                'pickup' => true,
            ],
            'credentials' => [
                ['key' => 'token', 'label' => 'API token', 'type' => 'password', 'required' => true,
                    'help' => 'PostEx merchant portal → Settings → API token.'],
            ],
            'settings' => [
                ['key' => 'pickup_address_code', 'label' => 'Pickup address code', 'type' => 'text',
                    'help' => 'The addressCode of your pickup address. "Test connection" lists your codes.'],
                ['key' => 'store_address_code', 'label' => 'Store address code', 'type' => 'text',
                    'help' => 'Optional, for store-wise pickups.'],
                ['key' => 'order_type', 'label' => 'Order type', 'type' => 'select', 'options' => [
                    ['value' => 'Normal', 'label' => 'Normal'],
                    ['value' => 'Reversed', 'label' => 'Reversed'],
                    ['value' => 'Replacement', 'label' => 'Replacement'],
                ], 'help' => 'Leave empty for Normal.'],
            ],
        ];
    }

    public function testConnection(): ConnectionResult
    {
        if ($missing = $this->missingCredentials()) {
            return ConnectionResult::failed('Missing: ' . implode(', ', $missing));
        }

        $res = $this->client()->get(self::BASE . 'v1/get-merchant-address');
        if (! $this->ok($res)) {
            return ConnectionResult::failed($this->error($res));
        }

        $codes = collect($res->json('dist') ?? [])
            ->map(fn ($a) => ($a['addressCode'] ?? '?') . ' (' . ($a['cityName'] ?? '') . ')')
            ->implode(', ');

        return ConnectionResult::ok('Connected to PostEx.' . ($codes ? " Pickup address codes: {$codes}." : ''));
    }

    public function book(ShipmentRequest $request): BookingResult
    {
        $body = array_filter([
            'orderRefNumber' => $request->reference,
            'invoicePayment' => round($request->codAmount, 2),
            'orderDetail' => $request->itemsDescription ?: null,
            'customerName' => $request->consigneeName,
            'customerPhone' => Phone::pk($request->consigneePhone),
            'deliveryAddress' => $request->address,
            'transactionNotes' => $request->notes ?: null,
            'cityName' => $request->courierCityName ?: $request->city,
            'invoiceDivision' => 1,
            'items' => $request->pieces,
            'pickupAddressCode' => $this->account->setting('pickup_address_code'),
            'storeAddressCode' => $this->account->setting('store_address_code'),
            'orderType' => $request->service ?: ($this->account->setting('order_type') ?: 'Normal'),
        ], fn ($v) => $v !== null && $v !== '');

        $res = $this->client()->post(self::BASE . 'v3/create-order', $body);
        $tracking = $res->json('dist.trackingNumber');
        if (! $this->ok($res) || ! $tracking) {
            throw new CourierException('PostEx: ' . $this->error($res));
        }

        return new BookingResult(
            trackingNumber: (string) $tracking,
            status: CourierStatus::BOOKED,
            rawStatus: $res->json('dist.orderStatus'),
            meta: ['order_date' => $res->json('dist.orderDate')],
        );
    }

    public function cancel(Shipment $shipment): void
    {
        $res = $this->client()->put(self::BASE . 'v1/cancel-order', ['trackingNumber' => $shipment->tracking_number]);
        // PostEx answers HTTP 200 with statusCode != "200" when it refuses.
        if (! $this->ok($res)) {
            throw new CourierException('PostEx: ' . $this->error($res));
        }
    }

    public function track(Shipment $shipment): TrackingResult
    {
        $res = $this->client()->get(self::BASE . 'v1/track-order/' . rawurlencode((string) $shipment->tracking_number));
        if (! $this->ok($res)) {
            throw new CourierException('PostEx: ' . $this->error($res));
        }
        $d = $res->json('dist') ?? [];

        $events = [];
        foreach ($d['transactionStatusHistory'] ?? [] as $h) {
            $text = $h['transactionStatusMessage'] ?? null;
            $code = (string) ($h['transactionStatusMessageCode'] ?? '');
            // The guide documents no time per history line; read one if present.
            $at = $h['updatedAt'] ?? $h['createdAt'] ?? $h['transactionDate'] ?? $h['date'] ?? null;
            $events[] = new TrackingEvent(
                status: self::HISTORY_CODES[$code] ?? StatusText::map($text),
                rawStatus: $text,
                at: $this->time($at),
                description: $code !== '' ? "Code {$code}" : null,
            );
        }

        $raw = $d['transactionStatus'] ?? null;
        $status = self::STATUSES[mb_strtolower(trim((string) $raw))] ?? StatusText::map($raw);

        $charges = null;
        $fees = (float) ($d['transactionFee'] ?? 0) + (float) ($d['transactionTax'] ?? 0)
            + (float) ($d['reversalFee'] ?? 0) + (float) ($d['reversalTax'] ?? 0);
        if ($fees > 0) {
            $charges = round($fees, 2);
        }

        [$codStatus, $codRef, $codAt] = $status === CourierStatus::DELIVERED ? $this->settlement($shipment) : [null, null, null];

        return new TrackingResult(
            status: $status,
            rawStatus: $raw,
            events: $events,
            deliveredAt: $status === CourierStatus::DELIVERED ? $this->time($d['orderDeliveryDate'] ?? null) : null,
            codStatus: $codStatus,
            codReference: $codRef,
            codRemittedAt: $codAt,
            charges: $charges,
        );
    }

    /** The airway bill as PDF bytes. */
    public function label(Shipment $shipment): array
    {
        $res = $this->client()->withHeaders(['Accept' => 'application/pdf'])
            ->get(self::BASE . 'v1/get-invoice', ['trackingNumbers' => $shipment->tracking_number]);

        if (! $res->successful() || ! str_contains(strtolower((string) $res->header('Content-Type')), 'pdf')) {
            throw new CourierException('PostEx: ' . $this->error($res));
        }

        return ['pdf' => $res->body()];
    }

    /** PostEx cities are names; the name doubles as the code. */
    public function cities(): array
    {
        $res = $this->client()->get(self::BASE . 'v2/get-operational-city', ['operationalCityType' => 'Delivery']);
        if (! $this->ok($res)) {
            throw new CourierException('PostEx: ' . $this->error($res));
        }

        return collect($res->json('dist') ?? [])
            ->filter(fn ($c) => ($c['isDeliveryCity'] ?? true) && ! empty($c['operationalCityName']))
            ->map(fn ($c) => ['code' => $c['operationalCityName'], 'name' => $c['operationalCityName']])
            ->values()->all();
    }

    // ------------------------------------------------------------------

    /** COD settlement: settle=true means PostEx has paid the merchant. */
    private function settlement(Shipment $shipment): array
    {
        try {
            $res = $this->client()->get(self::BASE . 'v1/payment-status/' . rawurlencode((string) $shipment->tracking_number));
            if (! $this->ok($res) || ! $res->json('dist.settle')) {
                return [null, null, null];
            }
            $d = $res->json('dist');

            return [
                'remitted',
                ($d['cprNumber_2'] ?? null) ?: ($d['cprNumber_1'] ?? null),
                $this->time($d['settlementDate'] ?? null),
            ];
        } catch (\Throwable $e) {
            // A settlement lookup failure must not lose the tracking update.
            return [null, null, null];
        }
    }

    private function ok(Response $res): bool
    {
        return $res->successful() && (string) $res->json('statusCode') === '200';
    }

    private function time($value): ?Carbon
    {
        try {
            return $value ? Carbon::parse($value) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function client()
    {
        return $this->http()->withHeaders(['token' => $this->account->credential('token')]);
    }

    private function error(Response $res): string
    {
        return $res->json('statusMessage') ?: $res->json('message') ?: ('HTTP ' . $res->status());
    }
}
