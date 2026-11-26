<?php

namespace Tests\Feature\Admin;

use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\ClientDocument;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PlatformManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_access_global_management_pages(): void
    {
        $superAdmin = User::factory()->create(['workspace_id' => null, 'role' => UserRole::SuperAdmin]);

        foreach (['/admin/documents', '/admin/tasks', '/admin/clients', '/admin/integrations', '/admin/mcp', '/admin/activity', '/admin/system', '/admin/settings', '/admin/connections', '/admin/upwork/configuration'] as $path) {
            $this->actingAs($superAdmin)->get($path)->assertOk();
        }

        $this->actingAs($superAdmin)->get('/admin/settings/storage')->assertRedirect('/admin/settings');
    }

    public function test_workspace_and_normal_users_cannot_access_super_admin_pages_or_apis(): void
    {
        $workspace = Workspace::query()->create(['name' => 'Workspace', 'slug' => 'workspace']);
        $admin = User::factory()->create(['workspace_id' => $workspace->id, 'role' => UserRole::Admin]);
        $user = User::factory()->create(['workspace_id' => $workspace->id, 'role' => UserRole::Employee]);

        foreach ([$admin, $user] as $actor) {
            $this->actingAs($actor)->get('/admin/documents')->assertForbidden();
            $this->actingAs($actor)->getJson('/admin/api/documents')->assertForbidden();
            $this->actingAs($actor)->get('/admin/mcp')->assertForbidden();
            $this->actingAs($actor)->getJson('/admin/api/mcp')->assertForbidden();
        }
    }

    public function test_super_admin_documents_api_returns_multiple_workspaces_and_filters(): void
    {
        [$superAdmin, $workspaceA, $workspaceB, $clientA, $clientB] = $this->platformFixtures();
        $creator = User::factory()->create(['workspace_id' => $workspaceA->id, 'role' => UserRole::Writer]);

        ClientDocument::query()->create([
            'workspace_id' => $workspaceA->id,
            'client_id' => $clientA->id,
            'title' => 'Local Report',
            'document_type' => 'report',
            'folder' => 'reports',
            'storage_disk' => 'local',
            'storage_path' => 'workspaces/a/report.pdf',
            'size_bytes' => 2048,
            'created_by' => $creator->id,
        ]);
        ClientDocument::query()->create([
            'workspace_id' => $workspaceB->id,
            'client_id' => $clientB->id,
            'title' => 'S3 Report',
            'document_type' => 'brief',
            'folder' => 'strategy',
            'storage_disk' => 's3',
            'storage_path' => 'workspaces/b/report.pdf',
        ]);

        $this->actingAs($superAdmin)
            ->getJson('/admin/api/documents')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonFragment(['title' => 'Local Report'])
            ->assertJsonFragment(['title' => 'S3 Report'])
            ->assertJsonFragment(['name' => $workspaceA->name])
            ->assertJsonFragment(['name' => $workspaceB->name]);

        $this->actingAs($superAdmin)
            ->getJson("/admin/api/documents?workspace_id={$workspaceA->id}&storage_disk=local")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.title', 'Local Report')
            ->assertJsonPath('data.0.storage_disk', 'local')
            ->assertJsonPath('data.0.size_bytes', 2048)
            ->assertJsonMissingPath('data.0.storage_path');
    }

    public function test_super_admin_tasks_api_returns_multiple_workspaces_and_filters(): void
    {
        [$superAdmin, $workspaceA, $workspaceB, $clientA, $clientB] = $this->platformFixtures();
        $assignee = User::factory()->create(['workspace_id' => $workspaceB->id, 'role' => UserRole::Employee]);

        Task::query()->create([
            'workspace_id' => $workspaceA->id,
            'client_id' => $clientA->id,
            'title' => 'Backlog task',
            'status' => TaskStatus::Backlog,
        ]);
        Task::query()->create([
            'workspace_id' => $workspaceB->id,
            'client_id' => $clientB->id,
            'assigned_to' => $assignee->id,
            'title' => 'Active task',
            'status' => TaskStatus::InProgress,
        ]);

        $this->actingAs($superAdmin)
            ->getJson('/admin/api/tasks')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonFragment(['title' => 'Backlog task'])
            ->assertJsonFragment(['title' => 'Active task']);

        $this->actingAs($superAdmin)
            ->getJson("/admin/api/tasks?workspace_id={$workspaceB->id}&status=in_progress")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.title', 'Active task')
            ->assertJsonPath('data.0.workspace.name', $workspaceB->name)
            ->assertJsonPath('data.0.assigned_to.name', $assignee->name);
    }

    public function test_global_clients_activity_mcp_filters_and_pagination(): void
    {
        [$superAdmin, $workspaceA, $workspaceB, $clientA, $clientB] = $this->platformFixtures();

        ActivityLog::query()->create(['workspace_id' => $workspaceA->id, 'action' => 'client.created', 'source' => 'portal']);
        ActivityLog::query()->create(['workspace_id' => $workspaceB->id, 'action' => 'mcp.request.failed', 'source' => 'mcp']);
        DB::table('client_integrations')->insert([
            'client_id' => $clientA->id,
            'provider' => 'google',
            'status' => 'error',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($superAdmin)
            ->getJson('/admin/api/clients?per_page=1')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.per_page', 1);

        $this->actingAs($superAdmin)
            ->getJson("/admin/api/clients?workspace_id={$workspaceB->id}")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.workspace.name', $workspaceB->name)
            ->assertJsonPath('data.0.documents_count', 0)
            ->assertJsonPath('data.0.tasks_count', 0);

        $this->actingAs($superAdmin)
            ->getJson("/admin/api/activity?workspace_id={$workspaceB->id}&search=mcp")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.action', 'mcp.request.failed');

        $this->actingAs($superAdmin)
            ->getJson('/admin/api/integrations?status=error')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.provider', 'google')
            ->assertJsonMissing(['encrypted_credentials']);

        $this->actingAs($superAdmin)
            ->getJson('/admin/api/mcp')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.tools.0', 'list_clients');
    }

    public function test_dashboard_counts_are_platform_wide(): void
    {
        [$superAdmin, $workspaceA, $workspaceB, $clientA, $clientB] = $this->platformFixtures();
        ClientDocument::query()->create(['workspace_id' => $workspaceA->id, 'client_id' => $clientA->id, 'title' => 'Doc A', 'document_type' => 'report', 'folder' => 'reports']);
        Task::query()->create(['workspace_id' => $workspaceB->id, 'client_id' => $clientB->id, 'title' => 'Task B', 'status' => TaskStatus::NextUp]);

        $response = $this->actingAs($superAdmin)->get('/admin/dashboard')->assertOk();

        $response->assertSee('Total Workspaces');
        $response->assertSee('Total Documents');
        $response->assertSee('Total Tasks');
        $response->assertSee('"value":2', false);
        $response->assertSee('"documents_count":1', false);
        $response->assertSee('"tasks_count":1', false);
    }

    private function platformFixtures(): array
    {
        $superAdmin = User::factory()->create(['workspace_id' => null, 'role' => UserRole::SuperAdmin]);
        $workspaceA = Workspace::query()->create(['name' => 'Alpha Workspace', 'slug' => 'alpha']);
        $workspaceB = Workspace::query()->create(['name' => 'Beta Workspace', 'slug' => 'beta']);
        $clientA = Client::query()->create(['workspace_id' => $workspaceA->id, 'name' => 'Alpha Client', 'slug' => 'alpha-client']);
        $clientB = Client::query()->create(['workspace_id' => $workspaceB->id, 'name' => 'Beta Client', 'slug' => 'beta-client']);

        return [$superAdmin, $workspaceA, $workspaceB, $clientA, $clientB];
    }
}
