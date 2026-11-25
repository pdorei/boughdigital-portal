<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\User;

/**
 * Called as [ClientAiConfig::class, $client]: a client always has a
 * configuration to view or save, even before the row exists.
 */
class ClientAiConfigPolicy
{
    public function before(User $user): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    public function view(User $user, Client $client): bool
    {
        return $user->belongsToWorkspace($client->workspace_id) && $user->canAccessClient($client->id);
    }

    public function update(User $user, Client $client): bool
    {
        return $user->role->canManageTasks() && $this->view($user, $client);
    }

    public function delete(User $user, Client $client): bool
    {
        return $user->belongsToWorkspace($client->workspace_id) && $user->role->canManageWorkspace();
    }
}
