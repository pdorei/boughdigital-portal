<?php

namespace App\Services\Content\Concerns;

use App\Models\Client;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * IDs arrive from the browser or an API client, so related records must be
 * confirmed to belong to the same client before they are stored.
 */
trait ChecksContentRelations
{
    private function pillarId(Client $client, mixed $id): ?int
    {
        if (blank($id)) {
            return null;
        }

        if (! $client->contentPillars()->whereKey($id)->exists()) {
            throw ValidationException::withMessages(['content_pillar_id' => 'Choose a content pillar of this client.']);
        }

        return (int) $id;
    }

    private function writerId(Client $client, mixed $id): ?int
    {
        if (blank($id)) {
            return null;
        }

        if (! User::query()->assignableToClient($client)->whereKey($id)->exists()) {
            throw ValidationException::withMessages(['assigned_writer_id' => 'Choose an active team member who can access this client.']);
        }

        return (int) $id;
    }
}
