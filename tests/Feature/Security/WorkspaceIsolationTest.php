<?php

namespace Tests\Feature\Security;

use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\Client;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkspaceIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_user_cannot_access_another_workspace_client_via_api(): void
    {
        [$workspaceA, $workspaceB, $adminA, , $clientB] = $this->fixture();

        $this->actingAs($adminA);

        $this->getJson("/{$workspaceA->slug}/api/clients/{$clientB->id}")
            ->assertNotFound();

        $this->putJson("/{$workspaceA->slug}/api/clients/{$clientB->id}", ['name' => 'Changed'])
            ->assertNotFound();
    }

    public function test_user_cannot_enter_workspace_without_membership(): void
    {
        [$workspaceA, $workspaceB, $adminA] = $this->fixture();

        $this->actingAs($adminA);

        $this->getJson("/{$workspaceB->slug}/api/clients")
            ->assertForbidden();

        $this->getJson("/{$workspaceA->slug}/api/clients")
            ->assertOk();
    }

    public function test_non_admin_only_sees_explicit_client_access(): void
    {
        [$workspaceA, , , $clientA1] = $this->fixture();
        $clientA2 = Client::query()->create([
            'workspace_id' => $workspaceA->id,
            'name' => 'Second Client',
            'slug' => 'second-client',
        ]);
        $writer = User::factory()->create([
            'workspace_id' => $workspaceA->id,
            'role' => UserRole::Writer,
        ]);
        $writer->clientAccess()->attach($clientA1->id, ['workspace_id' => $workspaceA->id]);

        $this->actingAs($writer);

        $this->getJson("/{$workspaceA->slug}/api/clients")
            ->assertOk()
            ->assertJsonPath('data.0.id', $clientA1->id)
            ->assertJsonMissing(['id' => $clientA2->id]);
    }

    public function test_mcp_requires_authentication(): void
    {
        [$workspaceA] = $this->fixture();

        $this->postJson("/mcp/{$workspaceA->slug}", [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
        ])->assertUnauthorized();
    }

    public function test_mcp_lists_only_visible_clients_for_authenticated_workspace_user(): void
    {
        [$workspaceA, , , $clientA1] = $this->fixture();
        Client::query()->create([
            'workspace_id' => $workspaceA->id,
            'name' => 'Hidden Client',
            'slug' => 'hidden-client',
        ]);
        $writer = User::factory()->create([
            'workspace_id' => $workspaceA->id,
            'role' => UserRole::Writer,
        ]);
        $writer->clientAccess()->attach($clientA1->id, ['workspace_id' => $workspaceA->id]);

        Sanctum::actingAs($writer);

        $response = $this->postJson("/mcp/{$workspaceA->slug}", [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/call',
            'params' => [
                'name' => 'list_clients',
                'arguments' => [],
            ],
        ])->assertOk()->json();

        $content = json_decode($response['result']['content'][0]['text'], true);

        $this->assertSame([$clientA1->id], array_column($content['clients'], 'id'));
    }

    public function test_mcp_rejects_missing_or_inactive_workspaces(): void
    {
        [$workspaceA, , $adminA] = $this->fixture();
        $workspaceA->forceFill(['is_active' => false])->save();

        Sanctum::actingAs($adminA);

        $this->postJson("/mcp/{$workspaceA->slug}", [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
        ])->assertNotFound();

        $this->postJson('/mcp/missing-workspace', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
        ])->assertNotFound();
    }

    public function test_old_workspace_prefixed_mcp_route_is_not_registered(): void
    {
        [$workspaceA, , $adminA] = $this->fixture();

        Sanctum::actingAs($adminA);

        $this->postJson("/{$workspaceA->slug}/mcp", [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
        ])->assertNotFound();
    }

    public function test_workspace_admin_cannot_manage_workspace_entity(): void
    {
        [$workspaceA, , $adminA] = $this->fixture();

        $this->assertFalse($adminA->can('update', $workspaceA));
        $this->assertFalse($adminA->can('create', Workspace::class));
    }

    public function test_super_admin_can_manage_workspace_entity(): void
    {
        [$workspaceA] = $this->fixture();
        $superAdmin = User::factory()->create([
            'workspace_id' => null,
            'role' => UserRole::SuperAdmin,
        ]);

        $this->assertTrue($superAdmin->can('update', $workspaceA));
        $this->assertTrue($superAdmin->can('create', Workspace::class));
    }

    private function fixture(): array
    {
        $workspaceA = Workspace::query()->create(['name' => 'Bough Digital', 'slug' => 'bough']);
        $workspaceB = Workspace::query()->create(['name' => 'Other Workspace', 'slug' => 'other']);

        $adminA = User::factory()->create([
            'workspace_id' => $workspaceA->id,
            'role' => UserRole::Admin,
        ]);

        $clientA = Client::query()->create([
            'workspace_id' => $workspaceA->id,
            'name' => 'Client A',
            'slug' => 'client-a',
        ]);

        $clientB = Client::query()->create([
            'workspace_id' => $workspaceB->id,
            'name' => 'Client B',
            'slug' => 'client-b',
        ]);

        Task::query()->create([
            'workspace_id' => $workspaceA->id,
            'client_id' => $clientA->id,
            'title' => 'Workspace A task',
            'status' => TaskStatus::Backlog,
        ]);

        Task::query()->create([
            'workspace_id' => $workspaceB->id,
            'client_id' => $clientB->id,
            'title' => 'Workspace B task',
            'status' => TaskStatus::Backlog,
        ]);

        return [$workspaceA, $workspaceB, $adminA, $clientA, $clientB];
    }
}
