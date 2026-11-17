<?php

namespace App\Support\Security;

use App\Models\OauthAccessToken;
use App\Models\OauthRefreshToken;
use App\Models\UserLoginSession;
use Illuminate\Support\Str;

/**
 * System Settings > Security "one active session per user", shared by every
 * staff sign-in path (web login, mobile app login, API token endpoint).
 */
final class SingleSession
{
    /**
     * Revoke every session and API token of the user except this device's.
     * Web sessions are destroyed here, SPA cookie sessions are refused by
     * EnforceApiTokenTimeout (and signed out on /login by the guest
     * middleware, see currentWebSessionRevoked); API tokens die now.
     *
     * @param  string[]  $keepKeys  user_login_sessions.access_token_id values of this device
     */
    public static function revokeOthers(int $userId, array $keepKeys = [], ?string $keepSessionId = null): void
    {
        // Web logins store their session id in access_token_id: end those
        // sessions on the server too, or the browser stays signed in.
        $webSessionIds = UserLoginSession::query()
            ->where('user_id', $userId)
            ->whereNull('revoked_at')
            ->where('access_token_id', 'not like', 'cookie:%')
            ->pluck('access_token_id')
            ->reject(fn ($id) => in_array($id, $keepKeys, true) || $id === $keepSessionId)
            ->all();
        self::destroySessions($webSessionIds);

        UserLoginSession::query()
            ->where('user_id', $userId)
            ->whereNull('revoked_at')
            ->when($keepSessionId !== null, fn ($q) => $q->where(fn ($qq) => $qq->whereNull('session_id')->orWhere('session_id', '!=', $keepSessionId)))
            ->when($keepKeys !== [], fn ($q) => $q->where(fn ($qq) => $qq->whereNull('access_token_id')->orWhereNotIn('access_token_id', $keepKeys)))
            ->update(['revoked_at' => now(), 'updated_at' => now()]);

        $tokenIds = OauthAccessToken::query()
            ->where('user_id', $userId)
            ->where('revoked', '=', 0)
            ->when($keepKeys !== [], fn ($q) => $q->whereNotIn('id', $keepKeys))
            ->pluck('id')
            ->all();

        if ($tokenIds) {
            OauthAccessToken::query()->whereIn('id', $tokenIds)->update(['revoked' => 1, 'updated_at' => now()]);
            OauthRefreshToken::query()->whereIn('access_token_id', $tokenIds)->update(['revoked' => 1]);
        }
    }

    /**
     * End every web session of the user on the server. A revoked
     * user_login_sessions row only blocks API calls (EnforceApiTokenTimeout);
     * the web session itself would stay signed in, and /login would bounce
     * the browser straight back to the app. Destroying the session data makes
     * the next page load land on the login form.
     */
    public static function destroyWebSessions(int $userId): void
    {
        // Web logins record their session id in access_token_id (session_id
        // is not fillable on the model); "cookie:…" rows are SPA tokens and
        // other ids are Passport tokens — destroying those is a no-op.
        $rows = UserLoginSession::query()
            ->where('user_id', $userId)
            ->get(['session_id', 'access_token_id']);

        self::destroySessions($rows->pluck('session_id')->merge($rows->pluck('access_token_id'))->all());
    }

    /**
     * Whether this browser's web session was signed out from elsewhere
     * (Login Devices, "one session per user", 2FA "Apply now"): its web
     * session row or its SPA cookie row ("cookie:" + sha256 of the CSRF
     * token) is revoked. Used on the guest pages so a revoked browser lands
     * on the login form instead of bouncing between /login and the app.
     */
    public static function currentWebSessionRevoked(\Illuminate\Http\Request $request, int $userId): bool
    {
        try {
            $sessionId = $request->session()->getId();
            $cookieKey = 'cookie:'.hash('sha256', (string) $request->session()->token());

            return UserLoginSession::query()
                ->where('user_id', $userId)
                ->whereNotNull('revoked_at')
                ->where(fn ($q) => $q->where('session_id', $sessionId)
                    ->orWhereIn('access_token_id', [$sessionId, $cookieKey]))
                ->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** @param  array<int, mixed>  $sessionIds */
    private static function destroySessions(array $sessionIds): void
    {
        $handler = app('session')->driver()->getHandler();

        foreach (array_unique($sessionIds) as $sessionId) {
            // Passport token ids and SPA "cookie:" keys are not sessions.
            if (! is_string($sessionId) || $sessionId === '' || str_starts_with($sessionId, 'cookie:')) {
                continue;
            }
            try {
                $handler->destroy($sessionId);
            } catch (\Throwable $e) {
                // best-effort: the revoked row still blocks the API
            }
        }
    }

    /**
     * A signed-out device whose browser was closed would otherwise come back
     * through its "remember me" cookie: a new token voids every such cookie.
     */
    public static function rotateRememberToken($user): void
    {
        $user->setRememberToken(Str::random(60));
        $user->save();
    }
}
