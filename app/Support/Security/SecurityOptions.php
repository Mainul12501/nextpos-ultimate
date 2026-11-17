<?php

namespace App\Support\Security;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Login security options (System Settings > Security), stored as JSON in
 * settings.security_options — except the inactivity timeout, which keeps its
 * own settings.session_timeout_minutes column because SetSessionConfig turns
 * it into the session lifetime on every request.
 *
 * Every read goes through sanitize(), so callers always get the full key set
 * with valid values. The CAPTCHA secret key is encrypted at rest and never
 * sent to the browser (forClient() swaps it for a "set" flag).
 */
final class SecurityOptions
{
    public const PROVIDERS = ['builtin', 'recaptcha', 'turnstile', 'hcaptcha'];

    public const CAPTCHA_MODES = ['always', 'after_failures'];

    public const LOCKOUT_SCOPES = ['email_ip', 'email', 'ip'];

    // Every toggle is off until the admin turns it on.
    public const DEFAULTS = [
        'captcha_enabled' => false,
        'captcha_provider' => 'builtin',
        'captcha_site_key' => '',
        'captcha_secret_key' => '',
        'captcha_mode' => 'always',
        'captcha_after_failures' => 3,
        'captcha_on_forgot_password' => false,

        'lockout_enabled' => false,
        'lockout_max_attempts' => 5,
        'lockout_minutes' => 10,
        'lockout_scope' => 'email_ip',
        'lockout_show_remaining' => false,

        'single_session' => false,
        'session_timeout_minutes' => 0,
        'log_login_attempts' => false,

        // Two-factor: users may always turn it on for themselves; this makes it
        // mandatory for the listed role ids ([] = every user).
        'two_factor_required' => false,
        'two_factor_roles' => [],
        // "Trust this device" skips the code for this many days (0 = never offer it).
        'two_factor_trust_days' => 30,
        // Days users who must have 2FA may skip setup ("Remind me later"),
        // counted from two_factor_required_since (0 = no grace period).
        'two_factor_grace_days' => 0,
        // Unix time the requirement was switched on; set by save(), never by the form.
        'two_factor_required_since' => 0,
    ];

    private const CACHE_KEY = 'login_security_options';

    private const CACHE_SECONDS = 60;

    public static function get(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, function () {
            [$json, $timeout] = self::readRaw();
            $options = self::sanitize($json);
            $options['session_timeout_minutes'] = self::sanitize(['session_timeout_minutes' => $timeout])['session_timeout_minutes'];

            return $options;
        });
    }

    /**
     * Merge a request payload over the stored options and persist them.
     * An empty secret keeps the stored one (the form never receives it).
     */
    public static function save(array $input): array
    {
        $stored = self::get();

        $secret = trim((string) ($input['captcha_secret_key'] ?? ''));
        $input['captcha_secret_key'] = $secret === ''
            ? $stored['captcha_secret_key']
            : Crypt::encryptString($secret);

        // The grace period runs from the moment the requirement is switched on.
        $requiredNow = filter_var($input['two_factor_required'] ?? $stored['two_factor_required'], FILTER_VALIDATE_BOOLEAN);
        $input['two_factor_required_since'] = ! $requiredNow
            ? 0
            : ($stored['two_factor_required'] && $stored['two_factor_required_since'] > 0 ? $stored['two_factor_required_since'] : time());

        $options = self::sanitize(array_merge($stored, $input));

        $json = $options;
        unset($json['session_timeout_minutes']);

        DB::table('settings')->whereNull('deleted_at')->update([
            'security_options' => json_encode($json),
            // null = never (SetSessionConfig then uses a one-year lifetime).
            'session_timeout_minutes' => $options['session_timeout_minutes'] > 0 ? $options['session_timeout_minutes'] : null,
        ]);

        self::forget();

        return $options;
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public static function secret(array $options): string
    {
        $value = (string) ($options['captcha_secret_key'] ?? '');
        if ($value === '') {
            return '';
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable $e) {
            // APP_KEY rotated: treat as unset so the admin re-enters it.
            return '';
        }
    }

    /** Safe to serialize to the settings page. */
    public static function forClient(array $options): array
    {
        $out = $options;
        $out['captcha_secret_set'] = self::secret($options) !== '';
        $out['captcha_secret_key'] = '';

        return $out;
    }

    /**
     * Turning on an external CAPTCHA with wrong keys would lock everyone out,
     * the admin included. So whenever the effective provider/keys change, the
     * settings page must send a token from a live widget built with the new
     * site key, and it is verified here with the new secret before saving.
     * Returns an error message, or null when the save may proceed.
     */
    public static function captchaKeyProblem(array $input, ?string $testToken, ?string $ip): ?string
    {
        $stored = self::get();
        $enabled = filter_var($input['captcha_enabled'] ?? $stored['captcha_enabled'], FILTER_VALIDATE_BOOLEAN);
        $provider = $input['captcha_provider'] ?? $stored['captcha_provider'];

        if (! $enabled || $provider === 'builtin') {
            return null;
        }

        $siteKey = trim((string) (array_key_exists('captcha_site_key', $input) ? $input['captcha_site_key'] : $stored['captcha_site_key']));
        $newSecret = trim((string) ($input['captcha_secret_key'] ?? ''));
        $secret = $newSecret !== '' ? $newSecret : self::secret($stored);

        if ($siteKey === '' || $secret === '') {
            return __('Enter both the site key and the secret key for the selected CAPTCHA provider.');
        }

        $unchanged = $stored['captcha_enabled']
            && $stored['captcha_provider'] === $provider
            && $stored['captcha_site_key'] === $siteKey
            && $newSecret === '';
        if ($unchanged) {
            return null;
        }

        if (! Captcha::verifyToken($provider, $secret, (string) $testToken, $ip)) {
            return __('Complete the CAPTCHA test with the new keys before saving. If the widget does not load, the site key is wrong or this domain is not allowed for it; if the check fails, the secret key is wrong.');
        }

        return null;
    }

    public static function sanitize($raw): array
    {
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }
        $raw = is_array($raw) ? $raw : [];
        $o = self::DEFAULTS;

        foreach (['captcha_enabled', 'captcha_on_forgot_password', 'lockout_enabled', 'lockout_show_remaining', 'single_session', 'log_login_attempts', 'two_factor_required'] as $key) {
            if (array_key_exists($key, $raw)) {
                $o[$key] = filter_var($raw[$key], FILTER_VALIDATE_BOOLEAN);
            }
        }

        $o['captcha_provider'] = self::oneOf($raw['captcha_provider'] ?? null, self::PROVIDERS, $o['captcha_provider']);
        $o['captcha_mode'] = self::oneOf($raw['captcha_mode'] ?? null, self::CAPTCHA_MODES, $o['captcha_mode']);
        $o['lockout_scope'] = self::oneOf($raw['lockout_scope'] ?? null, self::LOCKOUT_SCOPES, $o['lockout_scope']);

        $o['captcha_site_key'] = mb_substr(trim((string) ($raw['captcha_site_key'] ?? '')), 0, 255);
        $o['captcha_secret_key'] = (string) ($raw['captcha_secret_key'] ?? '');

        $o['captcha_after_failures'] = self::int($raw['captcha_after_failures'] ?? null, 1, 20, $o['captcha_after_failures']);
        $o['lockout_max_attempts'] = self::int($raw['lockout_max_attempts'] ?? null, 2, 50, $o['lockout_max_attempts']);
        $o['lockout_minutes'] = self::int($raw['lockout_minutes'] ?? null, 1, 1440, $o['lockout_minutes']);

        $o['two_factor_roles'] = array_values(array_unique(array_filter(
            array_map('intval', is_array($raw['two_factor_roles'] ?? null) ? $raw['two_factor_roles'] : []),
            fn ($id) => $id > 0
        )));
        $o['two_factor_trust_days'] = self::int($raw['two_factor_trust_days'] ?? null, 0, 90, $o['two_factor_trust_days']);
        $o['two_factor_grace_days'] = self::int($raw['two_factor_grace_days'] ?? null, 0, 30, $o['two_factor_grace_days']);
        $o['two_factor_required_since'] = self::int($raw['two_factor_required_since'] ?? null, 0, PHP_INT_MAX, 0);

        // 0 = never; otherwise at least 5 minutes so an admin can't make the
        // app unusable by saving a 1-minute timeout.
        $timeout = self::int($raw['session_timeout_minutes'] ?? null, 0, 43200, 0);
        $o['session_timeout_minutes'] = $timeout > 0 ? max(5, $timeout) : 0;

        return $o;
    }

    /** @return array{0: ?string, 1: mixed} [security_options JSON, session_timeout_minutes] */
    private static function readRaw(): array
    {
        $timeout = null;
        try {
            $timeout = DB::table('settings')->whereNull('deleted_at')->value('session_timeout_minutes');
        } catch (\Throwable $e) {
            // column missing on very old installs
        }

        try {
            return [DB::table('settings')->whereNull('deleted_at')->value('security_options'), $timeout];
        } catch (\Throwable $e) {
            // Column not migrated yet: everything stays off (DEFAULTS).
            return [null, $timeout];
        }
    }

    private static function oneOf($value, array $allowed, string $default): string
    {
        return in_array($value, $allowed, true) ? $value : $default;
    }

    private static function int($value, int $min, int $max, int $default): int
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return $default;
        }

        return max($min, min($max, (int) $value));
    }
}
