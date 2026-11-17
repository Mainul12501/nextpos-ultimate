<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Security\TwoFactor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;

/**
 * Profile > Two-factor authentication (the signed-in user's own 2FA), plus
 * the admin reset on the Users page. The sign-in step itself lives in
 * Auth\LoginController.
 *
 * Turning 2FA on is two calls: setup (password → new secret + otpauth URI,
 * held in the cache for SETUP_MINUTES) then confirm (a code from the app →
 * 2FA on + recovery codes, shown once). Disabling and new recovery codes
 * need the current password.
 */
class TwoFactorController extends Controller
{
    private const SETUP_MINUTES = 15;

    public function status(Request $request)
    {
        $user = $request->user('api');

        return response()->json($this->statusPayload($user));
    }

    public function setup(Request $request)
    {
        $user = $request->user('api');
        $this->ensureMigrated();
        $this->checkPassword($request, $user);

        if (TwoFactor::enabled($user)) {
            return response()->json(['message' => __('Two-factor authentication is already on.')], 422);
        }

        $setup = TwoFactor::newSetup($user);
        Cache::put($this->setupKey($user), Crypt::encryptString($setup['secret']), now()->addMinutes(self::SETUP_MINUTES));

        return response()->json($setup);
    }

    public function confirm(Request $request)
    {
        $request->validate(['code' => 'required|string|max:16']);
        $user = $request->user('api');
        $this->ensureMigrated();

        if (TwoFactor::enabled($user)) {
            return response()->json(['message' => __('Two-factor authentication is already on.')], 422);
        }

        try {
            $secret = Crypt::decryptString((string) Cache::get($this->setupKey($user)));
        } catch (\Throwable $e) {
            return response()->json(['message' => __('The setup expired. Start again.')], 422);
        }

        if (! TwoFactor::checkSetupCode($secret, (string) $request->input('code'))) {
            return response()->json(['message' => __('That code is not valid. Check the time on your phone and try again.')], 422);
        }

        Cache::forget($this->setupKey($user));
        $codes = TwoFactor::enable($user, $secret);

        return response()->json($this->statusPayload($user->fresh()) + ['recovery_codes' => $codes]);
    }

    public function regenerateRecoveryCodes(Request $request)
    {
        $user = $request->user('api');
        $this->checkPassword($request, $user);

        if (! TwoFactor::enabled($user)) {
            return response()->json(['message' => __('Two-factor authentication is off.')], 422);
        }

        $codes = TwoFactor::regenerateRecoveryCodes($user);

        return response()->json($this->statusPayload($user->fresh()) + ['recovery_codes' => $codes]);
    }

    public function disable(Request $request)
    {
        $user = $request->user('api');
        $this->checkPassword($request, $user);

        if (TwoFactor::required($user)) {
            return response()->json(['message' => __('Your administrator requires two-factor authentication for your account.')], 422);
        }

        TwoFactor::disable($user);

        return response()->json($this->statusPayload($user->fresh()));
    }

    /** Users page: clear a user's 2FA (lost phone and recovery codes). */
    public function reset(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'update', User::class);
        $this->ensureMigrated();

        $user = User::query()->whereNull('deleted_at')->findOrFail($id);
        TwoFactor::disable($user);

        return response()->json(['success' => true]);
    }

    private function statusPayload(User $user): array
    {
        $enabled = TwoFactor::enabled($user);

        return [
            'migrated' => TwoFactor::migrated(),
            'enabled' => $enabled,
            'required' => TwoFactor::required($user),
            'confirmed_at' => $enabled ? $user->two_factor_confirmed_at?->toDateTimeString() : null,
            'recovery_codes_left' => $enabled ? TwoFactor::recoveryCodesLeft($user) : 0,
            // Required but not set up yet: the deadline of the grace period, if any.
            'grace_until' => ! $enabled && TwoFactor::required($user) ? TwoFactor::graceUntil() : 0,
        ];
    }

    private function checkPassword(Request $request, User $user): void
    {
        $request->validate(['password' => 'required|string']);

        if (! Hash::check((string) $request->input('password'), $user->password)) {
            abort(response()->json(['message' => __('The password is incorrect.')], 422));
        }
    }

    private function ensureMigrated(): void
    {
        if (! TwoFactor::migrated()) {
            abort(response()->json(['message' => 'Run the database migrations first (php artisan migrate).'], 422));
        }
    }

    private function setupKey(User $user): string
    {
        return 'two_factor:setup:'.$user->id;
    }
}
