<?php

namespace App\Policies;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class WhatsAppSettingPolicy
{
    use HandlesAuthorization;

    protected function allowed(User $user): bool
    {
        $permission = Permission::where('name', 'whatsapp_settings')->first();

        // Installs upgraded before this permission existed fall back to the
        // owner role (role id 1) so they are never locked out.
        return $permission ? $user->hasRole($permission->roles) : $user->roles->contains('id', 1);
    }

    public function view(User $user)
    {
        return $this->allowed($user);
    }

    public function update(User $user)
    {
        return $this->allowed($user);
    }
}
