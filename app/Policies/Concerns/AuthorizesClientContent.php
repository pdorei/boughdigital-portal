<?php

namespace App\Policies\Concerns;

use App\Models\Client;
use App\Models\User;

/**
 * Content inherits its access from the client: anyone who can see the client
 * can read its content; admins and team leaders manage it; only admins delete.
 */
trait AuthorizesClientContent
{
    public function before(User $user): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    protected function canViewClient(User $user, Client $client): bool
    {
        return $user->belongsToWorkspace($client->workspace_id) && $user->canAccessClient($client->id);
    }

    protected function canManageClient(User $user, Client $client): bool
    {
        return $user->role->canManageTasks() && $this->canViewClient($user, $client);
    }

    protected function canDeleteFromClient(User $user, Client $client): bool
    {
        return $user->belongsToWorkspace($client->workspace_id) && $user->role->canManageWorkspace();
    }
}
