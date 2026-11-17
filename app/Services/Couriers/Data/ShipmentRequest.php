<?php

namespace App\Services\Couriers\Data;

/**
 * Everything a courier needs to book one parcel, already resolved from the
 * sale and the booking form (city matched, COD computed, defaults applied).
 */
final class ShipmentRequest
{
    public function __construct(
        public readonly string $reference,          // sale Ref, sent as the order number
        public readonly string $consigneeName,
        public readonly string $consigneePhone,
        public readonly string $address,
        public readonly string $city,               // Stocky-side city name
        public readonly ?string $courierCityCode = null,
        public readonly ?string $courierCityName = null,
        public readonly ?string $consigneeEmail = null,
        public readonly ?string $country = null,
        public readonly ?string $zip = null,
        public readonly float $codAmount = 0.0,     // 0 = prepaid
        public readonly float $declaredValue = 0.0,
        public readonly float $weight = 0.5,        // kg
        public readonly int $pieces = 1,
        public readonly string $itemsDescription = '',
        public readonly string $notes = '',
        public readonly ?string $service = null,
        public readonly bool $allowOpenParcel = false,
        public readonly ?string $trackingNumber = null, // manual courier: typed by staff
        public readonly ?string $state = null,
        public readonly ?float $latitude = null,      // couriers that route by map point
        public readonly ?float $longitude = null,
    ) {
    }

    /** ISO alpha-2 country of the receiver, $fallback when unknown. */
    public function countryCode(?string $fallback = null): ?string
    {
        return \App\Services\CountryService::toCode($this->country) ?? ($fallback ? strtoupper($fallback) : null);
    }

    public static function fromArray(array $d): self
    {
        return new self(
            reference: (string) ($d['reference'] ?? ''),
            consigneeName: (string) ($d['consignee_name'] ?? ''),
            consigneePhone: (string) ($d['consignee_phone'] ?? ''),
            address: (string) ($d['address'] ?? ''),
            city: (string) ($d['city'] ?? ''),
            courierCityCode: isset($d['courier_city_code']) ? (string) $d['courier_city_code'] : null,
            courierCityName: isset($d['courier_city_name']) ? (string) $d['courier_city_name'] : null,
            consigneeEmail: $d['consignee_email'] ?? null,
            country: $d['country'] ?? null,
            zip: $d['zip'] ?? null,
            codAmount: (float) ($d['cod_amount'] ?? 0),
            declaredValue: (float) ($d['declared_value'] ?? 0),
            weight: (float) ($d['weight'] ?? 0.5),
            pieces: max(1, (int) ($d['pieces'] ?? 1)),
            itemsDescription: (string) ($d['items_description'] ?? ''),
            notes: (string) ($d['notes'] ?? ''),
            service: $d['service'] ?? null,
            allowOpenParcel: (bool) ($d['allow_open_parcel'] ?? false),
            trackingNumber: isset($d['tracking_number']) ? trim((string) $d['tracking_number']) : null,
            state: isset($d['state']) && $d['state'] !== '' ? (string) $d['state'] : null,
            latitude: isset($d['latitude']) && is_numeric($d['latitude']) ? (float) $d['latitude'] : null,
            longitude: isset($d['longitude']) && is_numeric($d['longitude']) ? (float) $d['longitude'] : null,
        );
    }
}
