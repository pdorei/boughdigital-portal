<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\ClientCompetitor;
use App\Models\User;

/**
 * Competitors inherit their access from the owning client.
 */
class ClientCompetitorPolicy
{
    public function before(User $user): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    public function viewAny(User $user, Client $client): bool
    {
        return $user->belongsToWorkspace($client->workspace_id) && $user->canAccessClient($client->id);
    }

    public function view(User $user, ClientCompetitor $competitor): bool
    {
        return $this->viewAny($user, $competitor->client);
    }

    public function create(User $user, Client $client): bool
    {
        return $user->role->canManageTasks() && $this->viewAny($user, $client);
    }

    public function update(User $user, ClientCompetitor $competitor): bool
    {
        return $user->role->canManageTasks() && $this->view($user, $competitor);
    }

    public function delete(User $user, ClientCompetitor $competitor): bool
    {
        return $user->belongsToWorkspace($competitor->client->workspace_id) && $user->role->canManageWorkspace();
    }
}
