<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\ClientDocument;
use App\Models\User;
use App\Models\Workspace;

class ClientDocumentPolicy
{
    public function before(User $user): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    public function viewAny(User $user, Workspace $workspace): bool
    {
        return $user->belongsToWorkspace($workspace);
    }

    public function view(User $user, ClientDocument $document): bool
    {
        return $user->belongsToWorkspace($document->workspace_id) && $user->canAccessClient($document->client_id);
    }

    public function create(User $user, Client $client): bool
    {
        return $user->role->canManageTasks()
            && $user->belongsToWorkspace($client->workspace_id)
            && $user->canAccessClient($client->id);
    }

    public function update(User $user, ClientDocument $document): bool
    {
        return $user->role->canManageTasks() && $this->view($user, $document);
    }

    public function delete(User $user, ClientDocument $document): bool
    {
        return $user->belongsToWorkspace($document->workspace_id) && $user->role->canManageWorkspace();
    }
}
