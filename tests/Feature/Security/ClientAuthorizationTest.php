<?php

namespace Tests\Feature\Security;

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_crud_clients_in_any_workspace(): void
    {
        $workspace = Workspace::query()->create(['name' => 'Bough Digital', 'slug' => 'bough']);
        $superAdmin = User::factory()->create(['workspace_id' => null, 'role' => UserRole::SuperAdmin]);

        $this->actingAs($superAdmin);

        $created = $this->postJson("/{$workspace->slug}/api/clients", [
            'name' => 'Super Client',
            'website_url' => 'https://example.com',
        ])->assertCreated();

        $clientId = $created->json('data.id');

        $this->patchJson("/{$workspace->slug}/api/clients/{$clientId}", [
            'name' => 'Super Client Edited',
            'status' => 'active',
        ])->assertOk()
            ->assertJsonPath('data.name', 'Super Client Edited');
    }

    public function test_workspace_admin_can_crud_own_clients_but_not_other_workspace_clients(): void
    {
        $workspaceA = Workspace::query()->create(['name' => 'Bough Digital', 'slug' => 'bough']);
        $workspaceB = Workspace::query()->create(['name' => 'Agency Two', 'slug' => 'agency-two']);
        $adminA = User::factory()->create(['workspace_id' => $workspaceA->id, 'role' => UserRole::Admin]);
        $clientB = Client::query()->create(['workspace_id' => $workspaceB->id, 'name' => 'Other Client', 'slug' => 'other-client']);

        $this->actingAs($adminA);

        $created = $this->postJson("/{$workspaceA->slug}/api/clients", [
            'name' => 'Own Client',
            'website_url' => 'https://own.example',
        ])->assertCreated();

        $this->patchJson("/{$workspaceA->slug}/api/clients/{$created->json('data.id')}", [
            'name' => 'Own Client Edited',
            'status' => 'active',
        ])->assertOk();

        $this->getJson("/{$workspaceA->slug}/api/clients/{$clientB->id}")
            ->assertNotFound();

        $this->patchJson("/{$workspaceA->slug}/api/clients/{$clientB->id}", [
            'name' => 'Should Fail',
        ])->assertNotFound();
    }
}
