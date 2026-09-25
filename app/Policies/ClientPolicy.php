<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\User;
use App\Models\Workspace;

class ClientPolicy
{
    public function before(User $user): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    public function viewAny(User $user, Workspace $workspace): bool
    {
        return $user->belongsToWorkspace($workspace);
    }

    public function view(User $user, Client $client): bool
    {
        return $user->belongsToWorkspace($client->workspace_id) && $user->canAccessClient($client->id);
    }

    public function create(User $user, Workspace $workspace): bool
    {
        return $user->belongsToWorkspace($workspace) && $user->role->canManageWorkspace();
    }

    public function update(User $user, Client $client): bool
    {
        return $user->belongsToWorkspace($client->workspace_id) && $user->role->canManageWorkspace();
    }

    public function delete(User $user, Client $client): bool
    {
        return $this->update($user, $client);
    }
}
