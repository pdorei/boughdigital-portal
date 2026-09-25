<?php

namespace Tests\Concerns;

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\User;
use App\Models\Workspace;

trait CreatesWorkspaceFixtures
{
    protected function workspace(string $slug = 'bough'): Workspace
    {
        return Workspace::query()->create(['name' => ucfirst($slug), 'slug' => $slug]);
    }

    protected function member(Workspace $workspace, UserRole $role = UserRole::Admin, array $attributes = []): User
    {
        return User::factory()->create(['workspace_id' => $workspace->id, 'role' => $role, ...$attributes]);
    }

    protected function client(Workspace $workspace, string $slug = 'acorn-legal', array $attributes = []): Client
    {
        return Client::query()->create([
            'workspace_id' => $workspace->id,
            'name' => ucwords(str_replace('-', ' ', $slug)),
            'slug' => $slug,
            ...$attributes,
        ]);
    }

    protected function grantClientAccess(User $user, Client $client): void
    {
        $user->clientAccess()->attach($client->id, ['workspace_id' => $client->workspace_id]);
    }
}
