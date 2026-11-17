<?php

namespace App\Listeners\Security;

use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;

/**
 * Staff sign-ins and sign-outs in the audit log. Only App\Models\User is
 * recorded — storefront and portal customers authenticate through other
 * guards and are not staff activity.
 */
class RecordAuthAudit
{
    public function handleLogin(Login $event): void
    {
        if ($event->user instanceof User) {
            AuditLogger::record('login', $event->user);
        }
    }

    public function handleLogout(Logout $event): void
    {
        if ($event->user instanceof User) {
            AuditLogger::record('logout', $event->user);
        }
    }

    public function handleFailed(Failed $event): void
    {
        if ($event->user !== null && ! $event->user instanceof User) {
            return;
        }

        $email = (string) ($event->credentials['email'] ?? $event->credentials['username'] ?? '');

        AuditLogger::record('login_failed', $event->user, [], ['email' => $email], $email ?: null, [
            'user_id' => null,
            'user_name' => $email ?: null,
        ]);
    }
}
