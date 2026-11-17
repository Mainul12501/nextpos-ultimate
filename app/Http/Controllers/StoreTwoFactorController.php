<?php

namespace App\Http\Controllers;

use App\Models\StoreSetting;
use App\Support\Security\CustomerTwoFactor;
use App\Support\Security\TwoFactor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Online store customers' optional two-step sign-in:
 *  - the code step after a correct password (StoreAuthController::login
 *    parks the account via CustomerTwoFactor::startChallenge);
 *  - turning it on/off from My Account (email codes or an authenticator app).
 * Store Settings → "Two-step verification for customers" allows turning it on.
 */
class StoreTwoFactorController extends Controller
{
    private const SCOPE = 'store';

    // ------------------------------------------------------------ sign-in step

    /** GET login/two-factor */
    public function show(Request $request)
    {
        $pending = CustomerTwoFactor::pending($request, self::SCOPE);
        if (! $pending) {
            return $this->restart($request);
        }
        [$account, $data] = $pending;

        return view('store.auth.two-factor', [
            'emailFailed' => ! empty($data['email_failed']),
            's' => StoreSetting::first(),
            'method' => TwoFactor::method($account),
            'maskedEmail' => CustomerTwoFactor::maskEmail((string) $account->email),
            'trustDays' => TwoFactor::CUSTOMER_TRUST_DAYS,
        ]);
    }

    /** POST login/two-factor */
    public function verify(Request $request)
    {
        $pending = CustomerTwoFactor::pending($request, self::SCOPE);
        if (! $pending) {
            return $this->restart($request);
        }
        [$account, $data] = $pending;

        $request->validate(['code' => ['required', 'string', 'max:32']]);

        if ($error = CustomerTwoFactor::verifyChallenge($account, (string) $request->input('code'))) {
            return back()->withErrors(['code' => $error]);
        }

        CustomerTwoFactor::clearPending($request, self::SCOPE);
        Auth::guard('store')->login($account, (bool) ($data['remember'] ?? false));
        $request->session()->regenerate();

        $response = redirect()->to($this->safeRedirect((string) ($data['redirect'] ?? '')));
        if ($request->boolean('trust_device')) {
            $response->withCookie(TwoFactor::trustCookie($account, TwoFactor::CUSTOMER_TRUST_DAYS));
        }

        return $response;
    }

    /** POST login/two-factor/resend — email method only. */
    public function resend(Request $request)
    {
        $pending = CustomerTwoFactor::pending($request, self::SCOPE);
        if (! $pending) {
            return $this->restart($request);
        }
        [$account] = $pending;

        $sent = TwoFactor::sendEmailCode($account, CustomerTwoFactor::brand(self::SCOPE));
        if ($sent === 0) {
            $request->session()->put('store_two_factor_login.email_failed', false);
        }

        return back()->with($this->sendResult($sent));
    }

    // ------------------------------------------------------------ My Account

    /** POST account/two-factor/start {method, password} */
    public function start(Request $request)
    {
        $account = Auth::guard('store')->user();
        $data = $request->validate([
            'method' => ['required', 'in:email,app'],
            'password' => ['required', 'string'],
        ]);

        if (! CustomerTwoFactor::featureEnabled(self::SCOPE) || TwoFactor::enabled($account)) {
            return $this->toCard();
        }
        if (! Hash::check($data['password'], $account->password)) {
            return $this->toCard()->withErrors(['two_factor' => __('messages.TwoFactorWrongPassword')]);
        }

        $result = CustomerTwoFactor::startSetup($request, self::SCOPE, $account, $data['method']);

        return $this->toCard()->with($data['method'] === 'email' ? $this->sendResult($result['sent']) : []);
    }

    /** POST account/two-factor/confirm {code} */
    public function confirm(Request $request)
    {
        $account = Auth::guard('store')->user();
        $request->validate(['code' => ['required', 'string', 'max:16']]);

        $codes = CustomerTwoFactor::confirmSetup($request, self::SCOPE, $account, (string) $request->input('code'));
        if ($codes === null) {
            $setup = CustomerTwoFactor::setupInProgress($request, self::SCOPE, $account);

            return $this->toCard()->withErrors(['two_factor' => $setup && $setup['method'] === 'email'
                ? __('messages.TwoFactorEmailCodeInvalid')
                : __('messages.TwoFactorCodeInvalid')]);
        }

        return $this->toCard()
            ->with('two_factor_status', __('messages.TwoFactorTurnedOn'))
            ->with('two_factor_codes', $codes);
    }

    /** POST account/two-factor/cancel — abandon a setup in progress. */
    public function cancel(Request $request)
    {
        CustomerTwoFactor::cancelSetup($request, self::SCOPE);

        return $this->toCard();
    }

    /** POST account/two-factor/resend-setup — new email code during setup. */
    public function resendSetup(Request $request)
    {
        $account = Auth::guard('store')->user();
        $setup = CustomerTwoFactor::setupInProgress($request, self::SCOPE, $account);
        if (! $setup || $setup['method'] !== 'email') {
            return $this->toCard();
        }

        return $this->toCard()->with($this->sendResult(TwoFactor::sendEmailCode($account, CustomerTwoFactor::brand(self::SCOPE))));
    }

    /** POST account/two-factor/recovery-codes {password} */
    public function regenerate(Request $request)
    {
        $account = Auth::guard('store')->user();
        $request->validate(['password' => ['required', 'string']]);

        if (! TwoFactor::enabled($account) || TwoFactor::method($account) !== 'app') {
            return $this->toCard();
        }
        if (! Hash::check((string) $request->input('password'), $account->password)) {
            return $this->toCard()->withErrors(['two_factor' => __('messages.TwoFactorWrongPassword')]);
        }

        return $this->toCard()->with('two_factor_codes', TwoFactor::regenerateRecoveryCodes($account));
    }

    /** POST account/two-factor/disable {password} */
    public function disable(Request $request)
    {
        $account = Auth::guard('store')->user();
        $request->validate(['password' => ['required', 'string']]);

        if (! Hash::check((string) $request->input('password'), $account->password)) {
            return $this->toCard()->withErrors(['two_factor' => __('messages.TwoFactorWrongPassword')]);
        }

        TwoFactor::disable($account);

        return $this->toCard()->with('two_factor_status', __('messages.TwoFactorTurnedOff'));
    }

    // ------------------------------------------------------------ helpers

    private function sendResult(int $sent): array
    {
        if ($sent === 0) {
            return ['two_factor_sent' => true];
        }

        return ['two_factor_notice' => $sent > 0
            ? __('messages.TwoFactorResendWait', ['seconds' => $sent])
            : __('messages.TwoFactorEmailFailed')];
    }

    private function toCard()
    {
        return redirect()->to(route('account').'#two-factor');
    }

    private function restart(Request $request)
    {
        CustomerTwoFactor::clearPending($request, self::SCOPE);

        return redirect()->route('store.login.show')->withErrors(['email' => __('messages.TwoFactorExpired')]);
    }

    /** Same-origin paths only (as StoreAuthController::safeRedirect). */
    private function safeRedirect(string $redirect): string
    {
        if ($redirect !== '' && Str::startsWith($redirect, '/') && ! Str::startsWith($redirect, '//')) {
            return $redirect;
        }

        return route('checkout');
    }
}
