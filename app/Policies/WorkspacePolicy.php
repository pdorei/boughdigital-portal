<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Workspace;

/**
 * The workspace entity itself (name, slug, branding, lifecycle) is managed by
 * Super Admins only; workspace admins may change explicitly editable settings.
 */
class WorkspacePolicy
{
    public function before(User $user): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, Workspace $workspace): bool
    {
        return $user->belongsToWorkspace($workspace);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Workspace $workspace): bool
    {
        return false;
    }

    public function delete(User $user, Workspace $workspace): bool
    {
        return false;
    }

    public function updateSettings(User $user, Workspace $workspace): bool
    {
        return $user->belongsToWorkspace($workspace) && $user->role->canManageWorkspace();
    }

    public function viewActivity(User $user, Workspace $workspace): bool
    {
        return $this->updateSettings($user, $workspace);
    }
}
