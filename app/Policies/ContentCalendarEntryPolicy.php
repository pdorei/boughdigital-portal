<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\ContentCalendarEntry;
use App\Models\User;
use App\Policies\Concerns\AuthorizesClientContent;

class ContentCalendarEntryPolicy
{
    use AuthorizesClientContent;

    public function viewAny(User $user, Client $client): bool
    {
        return $this->canViewClient($user, $client);
    }

    public function view(User $user, ContentCalendarEntry $entry): bool
    {
        return $this->canViewClient($user, $entry->client);
    }

    public function create(User $user, Client $client): bool
    {
        return $this->canManageClient($user, $client);
    }

    public function update(User $user, ContentCalendarEntry $entry): bool
    {
        return $this->canManageClient($user, $entry->client);
    }

    public function delete(User $user, ContentCalendarEntry $entry): bool
    {
        return $this->canDeleteFromClient($user, $entry->client);
    }
}
