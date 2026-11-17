<?php

namespace App\Http\Middleware;

use App\Support\Security\SingleSession;
use Closure;
use Illuminate\Support\Facades\Auth;

class RedirectIfAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string|null  $guard
     * @return mixed
     */
    public function handle($request, Closure $next, $guard = null)
    {
        // If session is not valid, do not redirect
        if (! $request->hasSession() || ! $request->session()->isStarted()) {
            return $next($request);
        }
    
        // Staff browser signed out from elsewhere (Login Devices, one session
        // per user, 2FA "Apply now"): its API calls get 401 and send it to
        // /login, so end the web session here and show the form — otherwise
        // /login would bounce it back to the app in an endless loop.
        if (($guard === null || $guard === 'web') && ($user = Auth::guard('web')->user())
            && SingleSession::currentWebSessionRevoked($request, (int) $user->id)) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return $next($request);
        }

        // Extra safety: ensure user is REALLY authenticated
        if (Auth::guard($guard)->check() && $request->user()) {
            switch ($guard) {
                case 'store':
                    return redirect()->route('store.index');
                default:
                    return redirect('/next');
            }
        }
    
        return $next($request);
    }
    
}
