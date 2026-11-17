<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\EcommerceClient;
use App\Models\InviteCode;
use App\Models\StoreSetting;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreAuthController extends BaseController
{
    /**
     * Only allow same-origin path redirects.
     */
    protected function safeRedirect(Request $request): string
    {
        $redir = (string) $request->input('redirect', '');
        if ($redir && Str::startsWith($redir, ['/']) && ! Str::startsWith($redir, ['//'])) {
            return $redir;
        }

        if ($intended = (string) session('url.intended')) {
            if (Str::startsWith($intended, ['/']) && ! Str::startsWith($intended, ['//'])) {
                return $intended;
            }
        }

        return route('checkout');
    }

    /** GET /store/login */
    public function showLogin(Request $request)
    {
        $s = StoreSetting::first();
        $redirect = $this->safeRedirect($request);

        return view('store.auth.login', compact('s', 'redirect'));
    }

    /** POST /store/login */
    public function login(Request $request)
    {
        $cred = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
            'redirect' => ['nullable', 'string'],
        ]);

        $s = StoreSetting::first();
        $ecomClient = EcommerceClient::where('email', $cred['email'])->first();

        if ($ecomClient && Hash::check($cred['password'], $ecomClient->password)) {
            if ($ecomClient->is_blocked) {
                return back()
                    ->withErrors(['email' => __('messages.AccountBlockedMessage')])
                    ->onlyInput('email', '_form');
            }

            if ((int) $ecomClient->status === 0) {
                return back()
                    ->withErrors(['email' => __('messages.AccountPendingApproval')])
                    ->onlyInput('email', '_form');
            }
        }

        $attempt = [
            'email' => $cred['email'],
            'password' => $cred['password'],
            'status' => 1,
            'is_blocked' => 0,
        ];
        $remember = (bool) ($cred['remember'] ?? false);

        // Two-step verification: a correct password on an account with 2FA
        // only parks it; StoreTwoFactorController signs it in after the code.
        $guard = Auth::guard('store');
        if ($guard->validate($attempt)) {
            // The exact row the guard checked (active, not blocked, right password).
            $ecomClient = $guard->getLastAttempted();

            if (\App\Support\Security\CustomerTwoFactor::startChallenge($request, 'store', $ecomClient, $remember, [
                'redirect' => $this->safeRedirect($request),
            ])) {
                return redirect()->route('store.login.two-factor');
            }

            $guard->login($ecomClient, $remember);
            $request->session()->regenerate();

            return redirect()->to($this->safeRedirect($request));
        }

        return back()
            ->withErrors(['email' => __('Invalid credentials')])
            ->onlyInput('email', '_form');
    }

    /** GET /store/register */
    public function showRegister(Request $request)
    {
        $s = StoreSetting::first();
        $redirect = $this->safeRedirect($request);

        $registrationEnabled = $s->registration_enabled ?? true;
        $requireInviteCode = $s->require_invite_code ?? false;

        return view('store.auth.register', compact(
            's', 'redirect', 'registrationEnabled', 'requireInviteCode'
        ));
    }

    /** POST /store/register */
    public function register(Request $request)
    {
        $s = StoreSetting::first();

        if (! ($s->registration_enabled ?? true)) {
            return back()->withErrors(['registration' => __('messages.RegistrationDisabled')]);
        }

        $rules = [
            'name' => ['required', 'string', 'max:120'],
            'email' => [
                'required',
                'email',
                'max:190',
                Rule::unique('clients', 'email')->whereNull('deleted_at'),
                Rule::unique('ecommerce_clients', 'email')->whereNull('deleted_at'),
            ],
            'phone' => ['required', 'string', 'max:30'],
            'address' => ['required', 'string', 'max:190'],
            'password' => [
                'required',
                'confirmed',
                Password::min(8)->mixedCase()->numbers()->symbols(),
            ],
            'terms' => ['accepted'],
        ];

        if ($s->require_invite_code ?? false) {
            $rules['invite_code'] = ['required', 'string', 'max:64'];
        }

        $data = $request->validate($rules, [
            'terms.accepted' => __('messages.TermsRequired'),
        ]);

        $inviteCode = null;
        if ($s->require_invite_code ?? false) {
            $inviteCode = InviteCode::where('code', $data['invite_code'])->first();

            if (! $inviteCode || ! $inviteCode->isValid()) {
                return back()
                    ->withErrors(['invite_code' => __('messages.InvalidInviteCode')])
                    ->withInput($request->except('password', 'password_confirmation'));
            }
        }

        $requireApproval = $s->require_admin_approval ?? false;
        $initialStatus = $requireApproval ? 0 : 1;

        $user = DB::transaction(function () use ($data, $initialStatus, $inviteCode) {
            $client = Client::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'code' => $this->getNumberOrder(),
                    'phone' => $data['phone'],
                    'adresse' => $data['address'],
                ]
            );

            $ecomClient = EcommerceClient::create([
                'client_id' => $client->id,
                'username' => Str::slug(Str::before($data['email'], '@')),
                'email' => $data['email'],
                'status' => $initialStatus,
                'password' => Hash::make($data['password']),
                'invite_code_id' => $inviteCode?->id,
                'email_verified_at' => now(),
                'terms_accepted_at' => now(),
            ]);

            if ($inviteCode) {
                $inviteCode->redeem();
            }

            return $ecomClient;
        });

        if ($requireApproval) {
            return redirect()->route('store.login.show', ['redirect' => $this->safeRedirect($request)])
                ->with('pending_approval', true);
        }

        Auth::guard('store')->login($user);
        $request->session()->regenerate();

        return redirect()->to($this->safeRedirect($request));
    }

    /** GET /store/forgot-password */
    public function showForgotPassword(Request $request)
    {
        $s = StoreSetting::first();

        return view('store.auth.forgot-password', compact('s'));
    }

    /** POST /store/forgot-password — email a reset link (no account enumeration). */
    public function sendResetLink(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        $email = $data['email'];

        // Only issue a link for a real, non-blocked account — but the
        // response is identical regardless, so we never reveal whether it exists.
        $user = EcommerceClient::where('email', $email)->first();
        if ($user && ! $user->is_blocked) {
            $token = Str::random(64);

            DB::table('store_password_resets')->updateOrInsert(
                ['email' => $email],
                ['token' => Hash::make($token), 'created_at' => now()]
            );

            $this->sendResetEmail($email, $token, StoreSetting::first());
        }

        return back()->with('reset_link_sent', true);
    }

    /** GET /store/reset-password/{token} */
    public function showResetForm(Request $request, $token)
    {
        $s = StoreSetting::first();
        $email = (string) $request->query('email', '');

        return view('store.auth.reset-password', compact('s', 'token', 'email'));
    }

    /** POST /store/reset-password */
    public function resetPassword(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => [
                'required',
                'confirmed',
                Password::min(8)->mixedCase()->numbers()->symbols(),
            ],
        ]);

        $row = DB::table('store_password_resets')->where('email', $data['email'])->first();

        if (! $row || ! Hash::check($data['token'], $row->token)) {
            return back()->withErrors(['email' => __('messages.ResetLinkInvalid')]);
        }

        // Tokens expire after 60 minutes.
        if (\Carbon\Carbon::parse($row->created_at)->addMinutes(60)->isPast()) {
            DB::table('store_password_resets')->where('email', $data['email'])->delete();

            return back()->withErrors(['email' => __('messages.ResetLinkExpired')]);
        }

        $user = EcommerceClient::where('email', $data['email'])->first();
        if (! $user) {
            return back()->withErrors(['email' => __('messages.ResetLinkInvalid')]);
        }

        $user->password = Hash::make($data['password']);
        $user->save();

        DB::table('store_password_resets')->where('email', $data['email'])->delete();

        return redirect()->route('store.login.show')->with('password_reset_done', true);
    }

    /** Best-effort reset email; failures are logged, never thrown. */
    protected function sendResetEmail(string $email, string $token, ?StoreSetting $s = null): bool
    {
        try {
            (new \App\Http\Controllers\BaseController)->Set_config_mail();

            $url = route('store.password.reset', ['token' => $token]).'?email='.urlencode($email);

            Mail::to($email)->send(new \App\Mail\StorePasswordReset($url, $s->store_name ?? null));

            return true;
        } catch (\Throwable $e) {
            Log::warning('Store password reset email failed: '.$e->getMessage());

            return false;
        }
    }

    /** POST /store/logout */
    public function logout(Request $request)
    {
        Auth::guard('store')->logout();

        $request->session()->forget('guard_store');
        $request->session()->regenerateToken();

        return redirect()->route('store.login.show');
    }

    public function getNumberOrder()
    {
        $last = DB::table('clients')->latest('id')->first();

        if ($last) {
            $code = $last->code + 1;
        } else {
            $code = 1;
        }

        return $code;
    }
}
