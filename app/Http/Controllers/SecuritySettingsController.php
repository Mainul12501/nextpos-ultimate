<?php

namespace App\Http\Controllers;

use App\Models\LoginAttempt;
use App\Models\OauthAccessToken;
use App\Models\OauthRefreshToken;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserLoginSession;
use App\Support\Security\Captcha;
use App\Support\Security\LoginGuard;
use App\Support\Security\SecurityOptions;
use App\Support\Security\SingleSession;
use App\Support\Security\TwoFactor;
use Carbon\Carbon;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Http\Request;
use Laravel\Passport\Passport;
use App\Models\Setting;

class SecuritySettingsController extends Controller
{
    

    public function sessions(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'login_device_management', Setting::class);

        $user = $request->user('api');
        $currentSessionKey = $this->resolveCurrentSessionKey($request, (int) $user->id, $user->token());

        $tokens = OauthAccessToken::query()
            ->where('revoked', '=', 0)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->orderByDesc('created_at')
            ->get(['id', 'user_id', 'created_at', 'expires_at']);

        $tokenSessionMap = UserLoginSession::query()
            ->whereIn('access_token_id', $tokens->pluck('id')->all())
            ->get()
            ->keyBy('access_token_id');

        $userIds = $tokens->pluck('user_id')
            ->merge(UserLoginSession::query()
                ->whereNull('revoked_at')
                ->where('access_token_id', 'like', 'cookie:%')
                ->pluck('user_id'))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $userMap = User::query()
            ->whereIn('id', $userIds)
            ->get(['id', 'firstname', 'lastname', 'username'])
            ->keyBy('id');

        $resolveUserName = function ($userId) use ($userMap) {
            $u = $userMap->get((int) $userId);
            if (! $u) {
                return null;
            }
            $full = trim(((string) $u->firstname).' '.((string) $u->lastname));
            return $full !== '' ? $full : (string) $u->username;
        };

        $tokenSessions = $tokens->map(function ($token) use ($tokenSessionMap, $currentSessionKey, $resolveUserName) {
            $s = $tokenSessionMap->get($token->id);
            $ua = $s ? (string) ($s->user_agent ?? '') : '';

            return [
                'token_id' => (string) $token->id,
                'user_id' => (int) $token->user_id,
                'user_name' => $resolveUserName($token->user_id),
                'device' => $this->formatDeviceFromUserAgent($ua),
                'ip_address' => $s ? ($s->ip_address ?? null) : null,
                'login_at' => $token->created_at,
                'last_activity_at' => $s ? ($s->last_activity_at ?? null) : null,
                'is_current' => $currentSessionKey !== null && (string) $token->id === $currentSessionKey,
                'expires_at' => $token->expires_at,
            ];
        })->values();

        // Cookie-auth sessions (Passport TransientToken): stored with key "cookie:<sha256(csrf)>"
        $cookieSessions = UserLoginSession::query()
            ->whereNull('revoked_at')
            ->where('access_token_id', 'like', 'cookie:%')
            ->orderByDesc('last_activity_at')
            ->get(['user_id', 'access_token_id', 'ip_address', 'user_agent', 'logged_in_at', 'last_activity_at'])
            ->map(function ($s) use ($currentSessionKey, $resolveUserName) {
                $ua = (string) ($s->user_agent ?? '');
                return [
                    'token_id' => (string) $s->access_token_id,
                    'user_id' => (int) $s->user_id,
                    'user_name' => $resolveUserName($s->user_id),
                    'device' => $this->formatDeviceFromUserAgent($ua),
                    'ip_address' => $s->ip_address ?? null,
                    'login_at' => $s->logged_in_at,
                    'last_activity_at' => $s->last_activity_at,
                    'is_current' => $currentSessionKey !== null && (string) $s->access_token_id === $currentSessionKey,
                    'expires_at' => null,
                ];
            })
            ->values();

        $sessions = $cookieSessions->concat($tokenSessions)->values();

        return response()->json([
            'sessions' => $sessions,
        ]);
    }

    public function logoutSession(Request $request, string $tokenId)
    {
        $this->authorizeForUser($request->user('api'), 'login_device_management', Setting::class);

        $user = $request->user('api');
        $currentSessionKey = $this->resolveCurrentSessionKey($request, (int) $user->id, $user->token());

        if ($currentSessionKey !== null && $tokenId === $currentSessionKey) {
            return response()->json([
                'message' => 'You cannot logout the current session from this list.',
            ], 422);
        }

        // Cookie-auth session: just mark it revoked; EnforceApiTokenTimeout will block it.
        if (str_starts_with($tokenId, 'cookie:')) {
            $session = UserLoginSession::query()
                ->where('access_token_id', $tokenId)
                ->first();

            if (! $session) {
                return response()->json(['message' => 'Session not found.'], 404);
            }

            $session->revoked_at = now();
            $session->save();

            return response()->json(['success' => true]);
        }

        $token = OauthAccessToken::query()
            ->where('id', $tokenId)
            ->first();

        if (! $token) {
            return response()->json(['message' => 'Session not found.'], 404);
        }

        OauthAccessToken::query()->where('id', $tokenId)->update([
            'revoked' => 1,
            'updated_at' => now(),
        ]);
        OauthRefreshToken::query()->where('access_token_id', $tokenId)->update([
            'revoked' => 1,
        ]);

        UserLoginSession::query()->updateOrCreate(
            ['access_token_id' => $tokenId],
            [
                'user_id' => (int) $token->user_id,
                'revoked_at' => now(),
            ]
        );

        return response()->json(['success' => true]);
    }

    public function logoutAllOtherDevices(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'login_device_management', Setting::class);

        $user = $request->user('api');
        $currentSessionKey = $this->resolveCurrentSessionKey($request, (int) $user->id, $user->token());

        if ($currentSessionKey === null) {
            return response()->json(['message' => 'Unable to identify the current session.'], 422);
        }

        $otherTokenIds = OauthAccessToken::query()
            ->where('user_id', $user->id)
            ->where('revoked', '=', 0)
            ->where('id', '!=', $currentSessionKey)
            ->pluck('id')
            ->map(fn ($v) => (string) $v)
            ->all();

        // Revoke other cookie-auth sessions too
        $revokedCookieCount = UserLoginSession::query()
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->where('access_token_id', 'like', 'cookie:%')
            ->where('access_token_id', '!=', $currentSessionKey)
            ->update([
                'revoked_at' => now(),
                'updated_at' => now(),
            ]);

        if (empty($otherTokenIds) && ! $revokedCookieCount) {
            return response()->json(['success' => true, 'revoked' => 0]);
        }

        if (! empty($otherTokenIds)) {
            OauthAccessToken::query()->whereIn('id', $otherTokenIds)->update([
                'revoked' => 1,
                'updated_at' => now(),
            ]);
            OauthRefreshToken::query()->whereIn('access_token_id', $otherTokenIds)->update([
                'revoked' => 1,
            ]);
            UserLoginSession::query()->whereIn('access_token_id', $otherTokenIds)->update([
                'revoked_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return response()->json(['success' => true, 'revoked' => (count($otherTokenIds) + (int) $revokedCookieCount)]);
    }

    // ------------------------------------------------------------------
    // System Settings > Security: login CAPTCHA, lockout, session rules
    // ------------------------------------------------------------------

    public function getLoginSecurity(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'update', Setting::class);

        return response()->json([
            'options' => SecurityOptions::forClient(SecurityOptions::get()),
            'defaults' => SecurityOptions::forClient(SecurityOptions::DEFAULTS),
            'providers' => collect(Captcha::EXTERNAL)->map(fn ($p, $key) => [
                'value' => $key,
                'label' => $p['label'],
                'script' => $p['script'],
                'class' => $p['class'],
            ])->values(),
            'image_captcha' => Captcha::hasGd(),
            'migrated' => $this->securityMigrated(),
            'roles' => Role::query()->whereNull('deleted_at')->orderBy('name')->get(['id', 'name']),
            'two_factor' => $this->twoFactorStats(),
        ]);
    }

    public function updateLoginSecurity(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'update', Setting::class);

        if (! \Schema::hasColumn('settings', 'security_options')) {
            return response()->json(['message' => 'Run the database migrations first (php artisan migrate).'], 422);
        }

        $request->validate([
            'captcha_provider' => 'nullable|in:'.implode(',', SecurityOptions::PROVIDERS),
            'captcha_site_key' => 'nullable|string|max:255',
            'captcha_secret_key' => 'nullable|string|max:255',
            'captcha_mode' => 'nullable|in:'.implode(',', SecurityOptions::CAPTCHA_MODES),
            'captcha_after_failures' => 'nullable|integer|min:1|max:20',
            'lockout_max_attempts' => 'nullable|integer|min:2|max:50',
            'lockout_minutes' => 'nullable|integer|min:1|max:1440',
            'lockout_scope' => 'nullable|in:'.implode(',', SecurityOptions::LOCKOUT_SCOPES),
            'session_timeout_minutes' => 'nullable|integer|min:0|max:43200',
            'two_factor_roles' => 'nullable|array',
            'two_factor_roles.*' => 'integer',
            'two_factor_trust_days' => 'nullable|integer|min:0|max:90',
            'two_factor_grace_days' => 'nullable|integer|min:0|max:30',
        ]);

        $input = $request->only(array_keys(SecurityOptions::DEFAULTS));

        if ($problem = SecurityOptions::captchaKeyProblem($input, $request->input('captcha_test_token'), $request->ip())) {
            return response()->json(['message' => $problem], 422);
        }

        $options = SecurityOptions::save($input);

        return response()->json([
            'success' => true,
            'options' => SecurityOptions::forClient($options),
        ]);
    }

    /** How many active users have 2FA on, and how many still must set it up. */
    private function twoFactorStats(): array
    {
        if (! TwoFactor::migrated()) {
            return ['migrated' => false, 'enabled' => 0, 'total' => 0, 'pending' => 0, 'grace_until' => 0];
        }

        $users = User::query()->whereNull('deleted_at')->where('statut', 1);

        return [
            'migrated' => true,
            'enabled' => (clone $users)->whereNotNull('two_factor_confirmed_at')->count(),
            'total' => (clone $users)->count(),
            'pending' => $this->pendingTwoFactorUsers()->count(),
            'grace_until' => TwoFactor::graceUntil(),
        ];
    }

    /** Active users the saved settings require 2FA for who haven't set it up. */
    private function pendingTwoFactorUsers()
    {
        $options = SecurityOptions::get();

        return User::query()
            ->whereNull('deleted_at')
            ->where('statut', 1)
            ->whereNull('two_factor_confirmed_at')
            ->when(! $options['two_factor_required'], fn ($q) => $q->whereRaw('1 = 0'))
            ->when($options['two_factor_roles'] !== [], fn ($q) => $q->whereIn('role_id', $options['two_factor_roles']));
    }

    /**
     * "Apply now": sign out every user who must have 2FA but hasn't set it
     * up (web sessions, mobile/API tokens and remember-me cookies), so the
     * setup step runs at their next sign-in instead of whenever their current
     * session happens to end. The admin running it stays signed in.
     */
    public function applyTwoFactor(Request $request)
    {
        $admin = $request->user('api');
        $this->authorizeForUser($admin, 'update', Setting::class);

        if (! TwoFactor::migrated() || ! SecurityOptions::get()['two_factor_required']) {
            return response()->json(['message' => __('Turn on "Require two-factor authentication" and save first.')], 422);
        }

        $count = 0;
        $this->pendingTwoFactorUsers()
            ->where('id', '!=', (int) $admin->id)
            ->chunkById(200, function ($users) use (&$count) {
                foreach ($users as $user) {
                    SingleSession::destroyWebSessions((int) $user->id);
                    SingleSession::revokeOthers((int) $user->id);
                    SingleSession::rotateRememberToken($user);
                    $count++;
                }
            });

        return response()->json(['success' => true, 'signed_out' => $count, 'two_factor' => $this->twoFactorStats()]);
    }

    /**
     * Sign-in audit log + the lockouts that are active right now.
     * Filters: event, search (email/IP), page, limit.
     */
    public function loginAttempts(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'update', Setting::class);

        if (! $this->securityMigrated()) {
            return response()->json(['attempts' => [], 'totalRows' => 0, 'locked' => [], 'migrated' => false]);
        }

        $limit = max(1, min(100, (int) $request->input('limit', 20)));
        $page = max(1, (int) $request->input('page', 1));

        $base = LoginAttempt::query()
            ->when(in_array($request->input('event'), LoginAttempt::EVENTS, true), fn ($q) => $q->where('event', $request->input('event')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = '%'.str_replace(['%', '_'], ['\%', '\_'], (string) $request->input('search')).'%';
                $q->where(fn ($qq) => $qq->where('email', 'LIKE', $s)->orWhere('ip_address', 'LIKE', $s));
            });

        $total = (clone $base)->count();
        $rows = (clone $base)->orderByDesc('id')->offset(($page - 1) * $limit)->limit($limit)->get();

        $attempts = $rows->map(fn (LoginAttempt $a) => [
            'id' => $a->id,
            'email' => $a->email,
            'ip_address' => $a->ip_address,
            'device' => $this->formatDeviceFromUserAgent((string) $a->user_agent),
            'event' => $a->event,
            'created_at' => $a->created_at,
        ])->values();

        // From the lockout index in the cache, not the log: unblocking must
        // work even when sign-in activity recording is off.
        $locked = collect(LoginGuard::activeLocks())
            ->map(fn ($a) => [
                'email' => $a['email'],
                'ip_address' => $a['ip_address'],
                'locked_at' => date(DATE_ATOM, $a['locked_at']),
                'seconds_left' => $a['seconds_left'],
            ])
            ->values();

        return response()->json([
            'attempts' => $attempts,
            'totalRows' => $total,
            'locked' => $locked,
            'migrated' => true,
        ]);
    }

    public function unlockLogin(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'update', Setting::class);

        $request->validate([
            'email' => 'nullable|string|max:191',
            'ip_address' => 'nullable|ip',
        ]);

        if (! $request->filled('email') && ! $request->filled('ip_address')) {
            return response()->json(['message' => 'An email or IP address is required.'], 422);
        }

        LoginGuard::unlock($request->input('email'), $request->input('ip_address'));

        return response()->json(['success' => true]);
    }

    public function clearLoginAttempts(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'update', Setting::class);

        if ($this->securityMigrated()) {
            LoginAttempt::query()->delete();
        }

        return response()->json(['success' => true]);
    }

    private function securityMigrated(): bool
    {
        try {
            return \Schema::hasColumn('settings', 'security_options') && \Schema::hasTable('login_attempts');
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function resolveCurrentSessionKey(Request $request, int $userId, $passportToken): ?string
    {
        if ($passportToken instanceof EloquentModel) {
            $k = (string) $passportToken->getKey();
            return $k !== '' ? $k : null;
        }

        $jwtCookie = $request->cookie(Passport::cookie());
        if (! $jwtCookie) {
            return null;
        }

        try {
            /** @var Encrypter $encrypter */
            $encrypter = app(Encrypter::class);
            $raw = Passport::$decryptsCookies
                ? CookieValuePrefix::remove($encrypter->decrypt($jwtCookie, Passport::$unserializesCookies))
                : $jwtCookie;

            $decoded = (array) JWT::decode(
                $raw,
                new Key(Passport::tokenEncryptionKey($encrypter), 'HS256')
            );

            $sub = isset($decoded['sub']) ? (int) $decoded['sub'] : null;
            $csrf = isset($decoded['csrf']) ? (string) $decoded['csrf'] : null;
            $expiry = isset($decoded['expiry']) ? (int) $decoded['expiry'] : null;
            if ($sub !== $userId || ! $csrf || ! $expiry) {
                return null;
            }

            return 'cookie:'.hash('sha256', $csrf);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Get login activity report - shows all login sessions including historical ones
     */
    public function loginActivityReport(Request $request)
    {
        // Use login_device_management permission for now, or you can create a separate permission
        $this->authorizeForUser($request->user('api'), 'report_device_management', Setting::class);

        $user = $request->user('api');
        $currentSessionKey = $this->resolveCurrentSessionKey($request, (int) $user->id, $user->token());

        // Pagination parameters
        $perPage = $request->get('limit', 50);
        $pageStart = (int) $request->get('page', 1);

        // Get total count first
        $totalRows = UserLoginSession::query()
            ->where('user_id', $user->id)
            ->count();

        // Build query
        $query = UserLoginSession::query()
            ->where('user_id', $user->id)
            ->orderByDesc('logged_in_at');

        // Handle "ALL" option (-1) - show all records without pagination
        if ($perPage == '-1' || $perPage == -1) {
            // Don't apply offset/limit when showing all records
            $allSessions = $query->get();
        } else {
            // Apply pagination
            $perPage = (int) $perPage;
            $offSet = ($pageStart * $perPage) - $perPage;
            $allSessions = $query->offset($offSet)->limit($perPage)->get();
        }

        $sessions = $allSessions->map(function ($s) use ($currentSessionKey) {
            $ua = (string) ($s->user_agent ?? '');
            $isActive = false;
            
            // Check if session is active (not revoked)
            if ($s->revoked_at === null) {
                // For cookie sessions
                if (str_starts_with($s->access_token_id, 'cookie:')) {
                    $isActive = true;
                } else {
                    // For token sessions, check if token exists and is not revoked
                    $token = OauthAccessToken::query()
                        ->where('id', $s->access_token_id)
                        ->where('revoked', '=', 0)
                        ->where(function ($q) {
                            $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                        })
                        ->first();
                    $isActive = $token !== null;
                }
            }

            return [
                'token_id' => (string) $s->access_token_id,
                'device' => $this->formatDeviceFromUserAgent($ua),
                'ip_address' => $s->ip_address ?? null,
                'login_at' => $s->logged_in_at,
                'last_activity_at' => $s->last_activity_at,
                'revoked_at' => $s->revoked_at,
                'is_active' => $isActive,
                'is_current' => $currentSessionKey !== null && (string) $s->access_token_id === $currentSessionKey,
            ];
        })->values();

        return response()->json([
            'sessions' => $sessions,
            'totalRows' => $totalRows,
        ]);
    }

    private function formatDeviceFromUserAgent(string $ua): string
    {
        $uaLower = strtolower($ua);
        if ($uaLower === '') {
            return 'Unknown device';
        }

        $platform = 'Unknown OS';
        if (str_contains($uaLower, 'windows')) {
            $platform = 'Windows';
        } elseif (str_contains($uaLower, 'mac os') || str_contains($uaLower, 'macintosh')) {
            $platform = 'macOS';
        } elseif (str_contains($uaLower, 'android')) {
            $platform = 'Android';
        } elseif (str_contains($uaLower, 'iphone') || str_contains($uaLower, 'ipad') || str_contains($uaLower, 'ios')) {
            $platform = 'iOS';
        } elseif (str_contains($uaLower, 'linux')) {
            $platform = 'Linux';
        }

        $browser = 'Unknown Browser';
        if (str_contains($uaLower, 'edg/')) {
            $browser = 'Edge';
        } elseif (str_contains($uaLower, 'opr/') || str_contains($uaLower, 'opera')) {
            $browser = 'Opera';
        } elseif (str_contains($uaLower, 'chrome/') && ! str_contains($uaLower, 'edg/')) {
            $browser = 'Chrome';
        } elseif (str_contains($uaLower, 'safari/') && ! str_contains($uaLower, 'chrome/')) {
            $browser = 'Safari';
        } elseif (str_contains($uaLower, 'firefox/')) {
            $browser = 'Firefox';
        } elseif (str_contains($uaLower, 'msie') || str_contains($uaLower, 'trident/')) {
            $browser = 'Internet Explorer';
        }

        return $browser.' on '.$platform;
    }
}




