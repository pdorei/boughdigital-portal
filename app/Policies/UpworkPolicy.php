<?php

namespace App\Policies;

use App\Models\User;

/**
 * Upwork proposal automation is platform level (no workspace) and acts for the
 * agency's own Upwork account, so only Super Admins may use it. The same
 * applies to platform settings and connections, which hold API credentials.
 */
class UpworkPolicy
{
    public function manage(User $user): bool
    {
        return $user->isSuperAdmin() && $user->is_active;
    }

    public function manageSettings(User $user): bool
    {
        return $this->manage($user);
    }
}
