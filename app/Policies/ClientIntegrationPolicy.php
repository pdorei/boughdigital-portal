<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\ClientIntegration;
use App\Models\User;

/**
 * Anyone who can see the client can see integration status. Because they hold
 * credentials, only workspace admins may add, change or remove integrations.
 */
class ClientIntegrationPolicy
{
    public function before(User $user): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    public function viewAny(User $user, Client $client): bool
    {
        return $user->belongsToWorkspace($client->workspace_id) && $user->canAccessClient($client->id);
    }

    public function view(User $user, ClientIntegration $integration): bool
    {
        return $this->viewAny($user, $integration->client);
    }

    public function create(User $user, Client $client): bool
    {
        return $user->belongsToWorkspace($client->workspace_id) && $user->role->canManageWorkspace();
    }

    public function update(User $user, ClientIntegration $integration): bool
    {
        return $this->create($user, $integration->client);
    }

    public function delete(User $user, ClientIntegration $integration): bool
    {
        return $this->update($user, $integration);
    }
}
