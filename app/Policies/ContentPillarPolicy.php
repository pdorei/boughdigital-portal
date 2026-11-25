<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\ContentPillar;
use App\Models\User;
use App\Policies\Concerns\AuthorizesClientContent;

class ContentPillarPolicy
{
    use AuthorizesClientContent;

    public function viewAny(User $user, Client $client): bool
    {
        return $this->canViewClient($user, $client);
    }

    public function create(User $user, Client $client): bool
    {
        return $this->canManageClient($user, $client);
    }

    public function update(User $user, ContentPillar $pillar): bool
    {
        return $this->canManageClient($user, $pillar->client);
    }

    public function delete(User $user, ContentPillar $pillar): bool
    {
        return $this->canDeleteFromClient($user, $pillar->client);
    }
}
