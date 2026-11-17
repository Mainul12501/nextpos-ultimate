<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserLoginSession;
use App\Providers\RouteServiceProvider;
use App\Support\Security\LoginGuard;
use App\Support\Security\SingleSession;
use App\Support\Security\Totp;
use App\Support\Security\TwoFactor;
use Illuminate\Auth\Events\Attempting;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Validated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = RouteServiceProvider::HOME;

    /** Session key of a sign-in whose password was right but still needs its 2FA step. */
    private const PENDING = 'two_factor_login';

    /** Session key of the authenticator secret offered during forced setup. */
    private const SETUP_SECRET = 'two_factor_setup_secret';

    /** The 2FA step must be finished this long after the password. */
    private const PENDING_SECONDS = 600;

    /**
     * Create a new controller instance.
     *
     * @return void
     */

    /**
     * Get the needed authorization credentials from the request.
     *
     * @return array
     */
    protected function credentials(\Illuminate\Http\Request $request)
    {
        return ['email' => $request->{$this->username()}, 'password' => $request->password, 'statut' => 1];
    }

    public function __construct()
    {
        // captcha: also loaded by the forgot-password form.
        // showRecoveryCodes: right after forced 2FA setup, already signed in.
        $this->middleware('guest')->except(['logout', 'captcha', 'showRecoveryCodes']);
    }

    public function showLoginForm(Request $request)
    {
        // System Settings > Security
        $guard = LoginGuard::make();

        // Back from the 2FA step: start over.
        $request->session()->forget([self::PENDING, self::SETUP_SECRET]);

        return response()
            ->view('auth.login', [
                'captcha' => $guard->captchaRequired($request) ? $guard->captcha()->widget() : null,
                'rememberAllowed' => $guard->rememberAllowed(),
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    /** Built-in CAPTCHA image (System Settings > Security). */
    public function captcha(Request $request)
    {
        return response(LoginGuard::make()->captcha()->image($request), 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    /**
     * Handle a login request to the application.
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        // System Settings > Security: lockout first (so a blocked client can't
        // burn CAPTCHAs), then the CAPTCHA, then the password.
        $guard = LoginGuard::make();
        $email = (string) $request->input('email');

        $remember = (bool) $request->input('remember', false) && $guard->rememberAllowed();

        try {
            $guard->ensureNotLocked($request, $email);
            if ($guard->captchaRequired($request)) {
                $guard->verifyCaptcha($request, $email);
            }
        } catch (ValidationException $e) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['message' => collect($e->errors())->flatten()->first()], 422);
            }

            return back()->withErrors($e->errors())->withInput($request->only('email', 'remember'));
        }

        $user = $this->checkCredentials($request);

        if ($user) {
            // Two-factor: the password alone never signs in a user who has 2FA
            // (or must set it up); the session is only authenticated once the
            // code step passes.
            if ($this->needsTwoFactorStep($request, $user, $guard)) {
                $request->session()->forget(self::SETUP_SECRET);
                $request->session()->put(self::PENDING, [
                    'id' => (int) $user->id,
                    'remember' => $remember,
                    'at' => time(),
                ]);
                $request->session()->regenerate();

                return redirect()->route('login.two-factor');
            }

            return $this->completeLogin($request, $user, $remember);
        }

        // Failed login
        $message = $guard->recordFailure($request, $email);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'message' => $message,
            ], 422);
        }

        return back()->withErrors([
            'email' => $message,
        ])->withInput($request->only('email', 'remember'));
    }

    // ------------------------------------------------------------ two-factor

    /** Code entry (or forced authenticator setup) after a correct password. */
    public function showTwoFactor(Request $request)
    {
        $user = $this->pendingUser($request);
        if (! $user) {
            return $this->restartLogin($request);
        }

        return $this->twoFactorView($this->twoFactorState($request, $user));
    }

    public function twoFactor(Request $request)
    {
        $user = $this->pendingUser($request);
        if (! $user) {
            return $this->restartLogin($request);
        }

        $request->validate(['code' => ['required', 'string', 'max:32']]);

        $guard = LoginGuard::make();
        $remember = (bool) ($request->session()->get(self::PENDING)['remember'] ?? false);
        $code = (string) $request->input('code');
        $invalid = tdb('Two_factor_code_invalid', 'That code is not valid. Check the time on your phone and try again.');

        // Forced setup: the code proves the user's app holds the new secret.
        if (! TwoFactor::enabled($user)) {
            $secret = (string) $request->session()->get(self::SETUP_SECRET, '');
            if ($secret === '' || ! TwoFactor::checkSetupCode($secret, $code)) {
                return back()->withErrors(['code' => $invalid]);
            }

            $codes = TwoFactor::enable($user, $secret);
            $continue = $request->session()->pull('url.intended', url($this->redirectTo));
            $this->completeLogin($request, $user, $remember);

            return redirect()->route('login.two-factor.codes')
                ->with('two_factor_codes', $codes)
                ->with('two_factor_continue', $continue);
        }

        if ($seconds = TwoFactor::lockedFor($user)) {
            return back()->withErrors(['code' => TwoFactor::lockedMessage($seconds)]);
        }

        if (TwoFactor::verify($user, $code) === null) {
            $guard->recordTwoFactorFailure($request, (string) $user->email, (int) $user->id);
            $seconds = TwoFactor::lockedFor($user);

            return back()->withErrors(['code' => $seconds > 0 ? TwoFactor::lockedMessage($seconds) : $invalid]);
        }

        $response = $this->completeLogin($request, $user, $remember);

        $trustDays = $guard->options()['two_factor_trust_days'];
        if ($trustDays > 0 && $request->boolean('trust_device')) {
            $response->withCookie(TwoFactor::trustCookie($user, $trustDays));
        }

        return $response;
    }

    /** "Remind me later" on forced setup, allowed only during the grace period. */
    public function skipTwoFactorSetup(Request $request)
    {
        $user = $this->pendingUser($request);
        if (! $user) {
            return $this->restartLogin($request);
        }

        if (TwoFactor::enabled($user) || TwoFactor::mustSetUpNow($user)) {
            return redirect()->route('login.two-factor');
        }

        return $this->completeLogin($request, $user, (bool) ($request->session()->get(self::PENDING)['remember'] ?? false));
    }

    /** The new recovery codes, once, right after forced setup. */
    public function showRecoveryCodes(Request $request)
    {
        $codes = $request->session()->get('two_factor_codes');
        if (! Auth::guard('web')->check() || ! is_array($codes)) {
            return redirect($this->redirectTo);
        }

        return $this->twoFactorView([
            'mode' => 'codes',
            'codes' => $codes,
            'continue' => (string) $request->session()->get('two_factor_continue', url($this->redirectTo)),
        ]);
    }

    private function needsTwoFactorStep(Request $request, User $user, LoginGuard $guard): bool
    {
        if (! TwoFactor::migrated()) {
            return false;
        }

        if (TwoFactor::enabled($user)) {
            return ! TwoFactor::isTrusted($request, $user, $guard->options()['two_factor_trust_days']);
        }

        return TwoFactor::required($user, $guard->options());
    }

    private function pendingUser(Request $request): ?User
    {
        $pending = $request->session()->get(self::PENDING);
        if (! is_array($pending) || (int) ($pending['at'] ?? 0) < time() - self::PENDING_SECONDS) {
            return null;
        }

        $user = User::query()->whereKey((int) $pending['id'])->where('statut', 1)->first();

        // 2FA was reset while this step was open and isn't mandatory: there is
        // nothing to verify or set up, so the user signs in again normally.
        if ($user && ! TwoFactor::enabled($user) && ! TwoFactor::required($user)) {
            return null;
        }

        return $user;
    }

    private function restartLogin(Request $request)
    {
        $request->session()->forget([self::PENDING, self::SETUP_SECRET]);

        return redirect()->route('login')->withErrors([
            'email' => tdb('Two_factor_expired', 'Your sign-in expired. Please sign in again.'),
        ]);
    }

    private function twoFactorState(Request $request, User $user): array
    {
        if (TwoFactor::enabled($user)) {
            return [
                'mode' => 'challenge',
                'trustDays' => LoginGuard::make()->options()['two_factor_trust_days'],
            ];
        }

        // Forced setup: keep one secret for the whole attempt so a reload
        // doesn't invalidate the QR code already scanned.
        $secret = (string) $request->session()->get(self::SETUP_SECRET, '');
        if ($secret === '') {
            $secret = Totp::generateSecret();
            $request->session()->put(self::SETUP_SECRET, $secret);
        }

        return [
            'mode' => 'setup',
            'secret' => $secret,
            'uri' => Totp::uri($secret, (string) $user->email, TwoFactor::issuer()),
            // During the grace period the page offers "Remind me later".
            'graceUntil' => TwoFactor::inGrace() ? TwoFactor::graceUntil() : 0,
        ];
    }

    private function twoFactorView(array $state)
    {
        return response()
            ->view('auth.login', ['twoFactor' => $state, 'captcha' => null, 'rememberAllowed' => false])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    /**
     * Same rules and events as Auth::attempt() (active user, right password),
     * without signing in — the 2FA step may still be ahead. The events keep
     * listeners such as RecordAuthAudit ("login_failed") working.
     */
    private function checkCredentials(Request $request): ?User
    {
        $provider = Auth::guard('web')->getProvider();
        $credentials = $this->credentials($request);

        event(new Attempting('web', $credentials, false));
        $user = $provider->retrieveByCredentials($credentials);

        if ($user instanceof User && $provider->validateCredentials($user, $credentials)) {
            event(new Validated('web', $user));

            return $user;
        }

        event(new Failed('web', $user, $credentials));

        return null;
    }

    /** Sign the user in and do the post-login bookkeeping. */
    private function completeLogin(Request $request, User $user, bool $remember)
    {
        $guard = LoginGuard::make();

        Auth::guard('web')->login($user, $remember);
        $request->session()->forget([self::PENDING, self::SETUP_SECRET]);

        if ($guard->options()['single_session']) {
            $this->invalidateOtherRememberCookies($remember);
        }

        // Regenerate the web session ID to prevent fixation
        $request->session()->regenerate();

        $guard->recordSuccess($request, (string) $user->email, (int) $user->id);

        // Persist the current web session ID in user_login_sessions
        try {
            $sessionId = $request->session()->getId();

            UserLoginSession::query()->updateOrCreate(
                [
                    'user_id' => (int) $user->id,
                    'session_id' => $sessionId,
                ],
                [
                    'access_token_id' => $sessionId, // marker for web sessions
                    'ip_address' => $request->ip(),
                    'user_agent' => (string) ($request->userAgent() ?? ''),
                    'logged_in_at' => now(),
                    'last_activity_at' => now(),
                    'revoked_at' => null,
                ]
            );

            if ($guard->options()['single_session']) {
                $this->signOutOtherDevices((int) $user->id, $request);
            }
        } catch (\Throwable $e) {
            // Never break login if tracking fails
        }

        return redirect()->intended($this->redirectTo);
    }

    /**
     * "One session per user": a signed-out device whose browser was closed
     * would otherwise come back through its "remember me" cookie. A new
     * remember token voids every such cookie; this device gets a fresh one.
     */
    private function invalidateOtherRememberCookies(bool $remember): void
    {
        try {
            $user = Auth::guard('web')->user();
            SingleSession::rotateRememberToken($user);

            if ($remember) {
                Auth::guard('web')->login($user, true);
            }
        } catch (\Throwable $e) {
            // Never break login over this.
        }
    }

    /**
     * System Settings > Security "one session per user": revoke every other
     * device. This device is known by its web session id and by the SPA
     * cookie key ("cookie:" + sha256 of the CSRF token).
     */
    private function signOutOtherDevices(int $userId, Request $request): void
    {
        $sessionId = $request->session()->getId();
        $keep = [$sessionId, 'cookie:'.hash('sha256', (string) $request->session()->token())];

        SingleSession::revokeOthers($userId, $keep, $sessionId);
    }

    /**
     * Log the user out of the application.
     */
    public function logout(Request $request)
    {
        // 1) Explicitly log out the web guard
        $user = Auth::guard('web')->user();

        if ($user) {
            // Clear remember token to avoid automatic re-authentication
            $user->setRememberToken(null);
            $user->save();
        }

        Auth::guard('web')->logout();

        // 2) Fully invalidate the web session
        //    - clears all session data
        //    - regenerates the session ID
        $request->session()->invalidate();

        // 3) Regenerate CSRF token for the new empty session
        $request->session()->regenerateToken();

        // 4) For SPA (AJAX) logout calls, return JSON and let the frontend
        //    perform a full-page navigation with window.location.replace('/login')
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['success' => true]);
        }

        // 5) For classic form logouts, redirect to /login
        return redirect()->route('login');
    }
    
    
    

    /**
     * Get the login username to be used by the controller.
     */
    public function username()
    {
        return 'email';
    }
}
