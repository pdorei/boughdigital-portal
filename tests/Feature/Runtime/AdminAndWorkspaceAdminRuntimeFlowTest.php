<?php

namespace Tests\Feature\Runtime;

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAndWorkspaceAdminRuntimeFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_super_admin_can_use_platform_management_flows(): void
    {
        $this->seed();

        $workspace = Workspace::query()->where('slug', 'bough')->sole();

        $this->post('/login', [
            'email' => 'superadmin@example.com',
            'password' => 'password',
        ])->assertRedirect('/admin/dashboard');

        $this->get('/admin/dashboard')->assertOk();
        $this->get('/admin/workspaces')->assertOk();
        $this->get('/admin/users')->assertOk();

        $createUser = $this->postJson('/admin/api/users', [
            'workspace_id' => $workspace->id,
            'name' => 'Runtime Platform User',
            'email' => 'runtime.platform@example.com',
            'password' => 'runtime-password',
            'role' => UserRole::Writer->value,
            'is_active' => true,
        ])->assertCreated();

        $userId = $createUser->json('data.id');

        $this->patchJson("/admin/api/users/{$userId}", [
            'workspace_id' => $workspace->id,
            'name' => 'Runtime Platform User',
            'email' => 'runtime.platform@example.com',
            'role' => UserRole::TeamLeader->value,
            'is_active' => true,
        ])->assertOk()
            ->assertJsonPath('data.role', UserRole::TeamLeader->value);

        $this->postJson("/admin/api/users/{$userId}/deactivate")
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $client = Client::query()->whereBelongsTo($workspace)->firstOrFail();

        $this->getJson("/{$workspace->slug}/api/clients/{$client->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $client->id);
    }

    public function test_seeded_workspace_admin_can_manage_own_workspace_but_not_platform_or_cross_workspace(): void
    {
        $this->seed();

        $workspace = Workspace::query()->where('slug', 'bough')->sole();
        $otherWorkspace = Workspace::query()->create(['name' => 'Agency Two', 'slug' => 'agency-two']);
        $otherUser = User::factory()->create([
            'workspace_id' => $otherWorkspace->id,
            'role' => UserRole::Writer,
        ]);
        $otherClient = Client::query()->create([
            'workspace_id' => $otherWorkspace->id,
            'name' => 'Other Client',
            'slug' => 'other-client',
        ]);
        $client = Client::query()->whereBelongsTo($workspace)->firstOrFail();

        $this->post('/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ])->assertRedirect('/bough/dashboard');

        $this->get('/bough/team')->assertOk();
        $this->get('/bough/clients')->assertOk();
        $this->get('/admin/workspaces')->assertForbidden();

        $createUser = $this->postJson('/bough/api/users', [
            'name' => 'Runtime Workspace User',
            'email' => 'runtime.workspace@example.com',
            'password' => 'runtime-password',
            'role' => UserRole::Writer->value,
            'is_active' => true,
            'client_ids' => [$client->id],
        ])->assertCreated();

        $userId = $createUser->json('data.id');

        $this->patchJson("/bough/api/users/{$userId}", [
            'name' => 'Runtime Workspace User',
            'email' => 'runtime.workspace@example.com',
            'role' => UserRole::Employee->value,
            'is_active' => true,
            'client_ids' => [$client->id],
        ])->assertOk()
            ->assertJsonPath('data.role', UserRole::Employee->value);

        $this->postJson("/bough/api/users/{$userId}/deactivate")
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->postJson('/bough/api/users', [
            'name' => 'Blocked Super Admin',
            'email' => 'blocked.super@example.com',
            'password' => 'runtime-password',
            'role' => UserRole::SuperAdmin->value,
            'is_active' => true,
        ])->assertForbidden();

        $this->getJson("/bough/api/users/{$otherUser->id}")->assertNotFound();
        $this->getJson("/bough/api/clients/{$otherClient->id}")->assertNotFound();

        $createClient = $this->postJson('/bough/api/clients', [
            'name' => 'Runtime Client',
            'slug' => 'runtime-client',
            'website_url' => 'https://runtime.example',
            'status' => 'active',
        ])->assertCreated();

        $clientId = $createClient->json('data.id');

        $this->patchJson("/bough/api/clients/{$clientId}", [
            'name' => 'Runtime Client Updated',
            'slug' => 'runtime-client',
            'website_url' => 'https://runtime.example',
            'status' => 'paused',
        ])->assertOk()
            ->assertJsonPath('data.status', 'paused');
    }
}
