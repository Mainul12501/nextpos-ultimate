<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance as Middleware;

class CheckForMaintenanceMode extends Middleware
{
    /**
     * The URIs that should be reachable while maintenance mode is enabled.
     *
     * @var array
     */
    protected $except = [
        // System Update endpoints must stay reachable while the application
        // is down for updating: the admin UI drives the resumable update
        // steps and polls progress through them.
        'api/system-update/*',
        // The update page itself (SPA shell + what the SPA needs to boot:
        // auth check and translations). Every other page shows the
        // maintenance screen for the whole run.
        'next/settings/update',
        'api/get_user_auth',
        'api/translations/*',
        // Standalone recovery console (works without the SPA) + the login
        // routes needed to reach it if the admin session expired mid-update.
        'system-update/recovery',
        'login',
        'login/two-factor*',
        'logout',
    ];
}
