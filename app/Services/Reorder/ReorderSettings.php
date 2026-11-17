<?php

namespace App\Services\Reorder;

use App\Models\ProductReorderSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Smart reordering settings: global defaults (settings.reorder_settings JSON)
 * and optional per-product overrides (product_reorder_settings).
 *
 *  - sales_window_days: how far back sales are averaged (daily sales rate)
 *  - lead_time_days:    days between ordering and receiving
 *  - safety_days:       extra days of stock kept as a buffer
 *  - coverage_days:     days of sales one order should last once received
 *  - digest_enabled / digest_hour / digest_user_ids: the daily "need
 *    reordering" email (ReorderDigest), sent once a day at or after the hour
 *    to the chosen staff; digest_last_sent (Y-m-d) is internal bookkeeping.
 */
final class ReorderSettings
{
    public const DEFAULTS = [
        'sales_window_days' => 30,
        'lead_time_days' => 7,
        'safety_days' => 3,
        'coverage_days' => 30,
        'digest_enabled' => false,
        'digest_hour' => 8,
        'digest_user_ids' => [],
    ];

    private const LIMITS = [
        'sales_window_days' => [7, 365],
        'lead_time_days' => [0, 365],
        'safety_days' => [0, 365],
        'coverage_days' => [1, 365],
    ];

    public static function migrated(): bool
    {
        static $migrated = null;

        try {
            return $migrated ??= Schema::hasTable('product_reorder_settings');
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function defaults(): array
    {
        $raw = null;
        try {
            $raw = DB::table('settings')->whereNull('deleted_at')->value('reorder_settings');
        } catch (\Throwable $e) {
            // column not migrated yet
        }

        return self::sanitize(is_string($raw) ? (json_decode($raw, true) ?: []) : []);
    }

    public static function saveDefaults(array $input): array
    {
        $stored = self::defaults();
        $clean = self::sanitize(array_merge($stored, $input));
        // Bookkeeping, never taken from the form.
        $clean['digest_last_sent'] = $stored['digest_last_sent'];
        self::write($clean);

        return $clean;
    }

    /** Record that today's digest went out (or was skipped as empty). */
    public static function markDigestSent(string $date): void
    {
        $settings = self::defaults();
        $settings['digest_last_sent'] = $date;
        self::write($settings);
    }

    private static function write(array $settings): void
    {
        DB::table('settings')->whereNull('deleted_at')->update(['reorder_settings' => json_encode($settings)]);
    }

    /** The product's overrides for the product form (all null when none saved). */
    public static function forProduct(?int $productId): array
    {
        $row = $productId && self::migrated()
            ? ProductReorderSetting::where('product_id', $productId)->first()
            : null;

        return [
            'preferred_provider_id' => $row?->preferred_provider_id,
            'lead_time_days' => $row?->lead_time_days,
            'safety_days' => $row?->safety_days,
            'min_order_qty' => $row?->min_order_qty,
            'order_multiple' => $row?->order_multiple,
            'excluded' => (bool) ($row?->excluded ?? false),
        ];
    }

    /**
     * Save the product form's "Reordering" card (JSON string or array).
     * An all-empty card deletes the row, so the product follows the defaults.
     */
    public static function saveForProduct(int $productId, $input): void
    {
        if (! self::migrated()) {
            return;
        }

        $input = is_string($input) ? json_decode($input, true) : $input;
        if (! is_array($input)) {
            return;
        }

        $int = fn ($v, $max) => ($v === null || $v === '' || ! is_numeric($v)) ? null : max(0, min($max, (int) $v));
        $qty = fn ($v) => ($v === null || $v === '' || ! is_numeric($v) || (float) $v <= 0) ? null : round((float) $v, 4);

        $values = [
            'preferred_provider_id' => ! empty($input['preferred_provider_id']) ? (int) $input['preferred_provider_id'] : null,
            'lead_time_days' => $int($input['lead_time_days'] ?? null, 365),
            'safety_days' => $int($input['safety_days'] ?? null, 365),
            'min_order_qty' => $qty($input['min_order_qty'] ?? null),
            'order_multiple' => $qty($input['order_multiple'] ?? null),
            'excluded' => filter_var($input['excluded'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ];

        $empty = ! $values['excluded'] && count(array_filter($values, fn ($v) => $v !== null && $v !== false)) === 0;
        if ($empty) {
            ProductReorderSetting::where('product_id', $productId)->delete();

            return;
        }

        ProductReorderSetting::updateOrCreate(['product_id' => $productId], $values);
    }

    private static function sanitize(array $raw): array
    {
        $out = [];
        foreach (self::LIMITS as $key => [$min, $max]) {
            $v = $raw[$key] ?? null;
            $out[$key] = is_numeric($v) ? max($min, min($max, (int) $v)) : self::DEFAULTS[$key];
        }

        $out['digest_enabled'] = filter_var($raw['digest_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $hour = $raw['digest_hour'] ?? null;
        $out['digest_hour'] = is_numeric($hour) ? max(0, min(23, (int) $hour)) : self::DEFAULTS['digest_hour'];
        $out['digest_user_ids'] = array_values(array_unique(array_filter(
            array_map('intval', is_array($raw['digest_user_ids'] ?? null) ? $raw['digest_user_ids'] : []),
            fn ($id) => $id > 0
        )));
        $last = (string) ($raw['digest_last_sent'] ?? '');
        $out['digest_last_sent'] = preg_match('/^\d{4}-\d{2}-\d{2}$/', $last) ? $last : null;

        return $out;
    }
}
