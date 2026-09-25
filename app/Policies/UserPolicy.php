<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\Workspace;

class UserPolicy
{
    public function before(User $user): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    public function viewAny(User $user, ?Workspace $workspace = null): bool
    {
        return $this->managesWorkspace($user, $workspace);
    }

    public function create(User $user, ?Workspace $workspace = null): bool
    {
        return $this->managesWorkspace($user, $workspace);
    }

    public function view(User $user, User $member): bool
    {
        return $this->managesMember($user, $member);
    }

    public function update(User $user, User $member): bool
    {
        return $this->managesMember($user, $member);
    }

    public function deactivate(User $user, User $member): bool
    {
        return $this->managesMember($user, $member) && ! $user->is($member);
    }

    public function removeFromWorkspace(User $user, User $member): bool
    {
        return $this->deactivate($user, $member);
    }

    /**
     * Deleting a platform account is reserved for Super Admins (see before()).
     */
    public function delete(User $user, User $member): bool
    {
        return false;
    }

    /**
     * Workspace admins cannot take over a fellow admin's account by changing
     * its email or password.
     */
    public function changeCredentials(User $user, User $member): bool
    {
        return $this->managesMember($user, $member)
            && ($user->is($member) || ! $member->hasRole(UserRole::Admin));
    }

    public function assignRole(User $user, UserRole $role, ?Workspace $workspace = null): bool
    {
        return $role !== UserRole::SuperAdmin && $this->managesWorkspace($user, $workspace);
    }

    private function managesWorkspace(User $user, ?Workspace $workspace): bool
    {
        return $workspace !== null
            && $user->role->canManageWorkspace()
            && $user->belongsToWorkspace($workspace);
    }

    private function managesMember(User $user, User $member): bool
    {
        return $user->role->canManageWorkspace()
            && $user->workspace_id !== null
            && (int) $user->workspace_id === (int) $member->workspace_id
            && ! $member->isSuperAdmin();
    }
}
