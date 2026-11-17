<?php

namespace App\Support;

use App\Http\Middleware\SetLocale;
use App\Models\Setting;

/**
 * Client Portal options, stored as one JSON blob in settings.portal_settings
 * and edited on Client Portal → Portal Settings.
 *
 * A null column or a missing key means the default below, so installs that
 * never opened the page keep today's behaviour: portal on, every module shown.
 * Enforcement lives in the `portal.feature` middleware (routes/portal.php);
 * the portal SPA only hides what the API would refuse anyway.
 */
class PortalSettings
{
    /** Client-facing modules that can be switched off, in portal sidebar order. */
    public const MODULES = [
        'invoices', 'payments', 'statement', 'requests', 'quotations',
        'appointments', 'contracts', 'rentals', 'help',
    ];

    /** Actions clients can take, each switchable on its own. */
    public const ACTIONS = ['quotation_requests', 'appointment_booking'];

    private static ?array $cache = null;

    public static function defaults(): array
    {
        return [
            'enabled' => true,
            'modules' => array_fill_keys(self::MODULES, true),
            'actions' => array_fill_keys(self::ACTIONS, true),
            'welcome_message' => '',
            'default_locale' => null,
            'invitation_expiry_hours' => 48,
            // Lets portal users turn on two-step sign-in from their profile.
            'two_factor_enabled' => false,
        ];
    }

    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $stored = [];
        try {
            // Runs on every portal request (SetPortalLocale), so no schema
            // check up front — a missing column just throws and lands below.
            $raw = Setting::whereNull('deleted_at')->value('portal_settings');
            $stored = $raw ? (json_decode($raw, true) ?: []) : [];
        } catch (\Throwable $e) {
            // Not installed / migration not run yet: fall back to defaults.
        }

        return self::$cache = self::normalize($stored);
    }

    public static function save(array $input): array
    {
        $clean = self::normalize($input);
        Setting::whereNull('deleted_at')->update(['portal_settings' => json_encode($clean)]);
        self::$cache = $clean;

        return $clean;
    }

    public static function enabled(): bool
    {
        return (bool) self::all()['enabled'];
    }

    /** True when every named module / action is switched on. */
    public static function allows(string ...$features): bool
    {
        $s = self::all();
        foreach ($features as $f) {
            $on = $s['modules'][$f] ?? $s['actions'][$f] ?? true;
            if (! $on) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether a portal SPA path (e.g. '/invoices', '/rental-invoices?status=open')
     * belongs to a switched-on module — used to drop dashboard alerts and bell
     * items that would point at a hidden section. Mirrors portal/lib/settings.js.
     */
    public static function allowsPath(?string $url): bool
    {
        $first = explode('/', trim((string) parse_url((string) $url, PHP_URL_PATH), '/'))[0] ?? '';
        $map = ['rental-invoices' => 'rentals', 'rental-requests' => 'rentals'];
        $module = $map[$first] ?? (in_array($first, self::MODULES, true) ? $first : null);

        return $module === null || self::allows($module);
    }

    public static function invitationExpiryHours(): int
    {
        return (int) self::all()['invitation_expiry_hours'];
    }

    public static function defaultLocale(): ?string
    {
        return self::all()['default_locale'];
    }

    /** The subset the portal SPA needs (sent with /api/portal/me). */
    public static function forClient(): array
    {
        $s = self::all();

        return [
            'modules' => $s['modules'],
            'actions' => $s['actions'],
            'welcome_message' => $s['welcome_message'],
            'two_factor_enabled' => $s['two_factor_enabled'],
        ];
    }

    /** Merge input over defaults, dropping unknown keys and bad values. */
    private static function normalize(array $in): array
    {
        $d = self::defaults();

        $flags = function ($given, array $keys) {
            $given = is_array($given) ? $given : [];
            $out = [];
            foreach ($keys as $k) {
                $out[$k] = array_key_exists($k, $given) ? (bool) $given[$k] : true;
            }

            return $out;
        };

        $locale = $in['default_locale'] ?? null;
        $hours = (int) ($in['invitation_expiry_hours'] ?? $d['invitation_expiry_hours']);

        return [
            'enabled' => array_key_exists('enabled', $in) ? (bool) $in['enabled'] : true,
            'modules' => $flags($in['modules'] ?? null, self::MODULES),
            'actions' => $flags($in['actions'] ?? null, self::ACTIONS),
            'welcome_message' => mb_substr(trim((string) ($in['welcome_message'] ?? '')), 0, 2000),
            // Any INSTALLED portal language (Client Portal → Portal Languages).
            'default_locale' => $locale && isset(\App\Services\PortalLanguageInstaller::installedLocales()[$locale]) ? $locale : null,
            'invitation_expiry_hours' => max(1, min(720, $hours ?: 48)),
            'two_factor_enabled' => (bool) ($in['two_factor_enabled'] ?? false),
        ];
    }
}
