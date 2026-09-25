<?php

namespace App\Support\Routing;

use App\Models\User;

class HomePath
{
    /**
     * Null when a non-platform user has no active workspace to land in.
     */
    public static function for(User $user): ?string
    {
        if ($user->isSuperAdmin()) {
            return '/admin/dashboard';
        }

        $workspace = $user->activeWorkspace();

        return $workspace ? "/{$workspace->slug}/dashboard" : null;
    }
}
