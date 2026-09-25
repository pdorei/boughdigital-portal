<?php

namespace Tests\Feature\Users;

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\ClientDocument;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UserCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_manage_users_across_workspaces(): void
    {
        $workspaceA = Workspace::query()->create(['name' => 'Bough Digital', 'slug' => 'bough']);
        $workspaceB = Workspace::query()->create(['name' => 'Agency Two', 'slug' => 'agency-two']);
        $superAdmin = User::factory()->create(['workspace_id' => null, 'role' => UserRole::SuperAdmin]);

        $this->actingAs($superAdmin);

        $created = $this->postJson('/admin/api/users', [
            'workspace_id' => $workspaceA->id,
            'name' => 'New Workspace User',
            'email' => 'new@example.com',
            'password' => 'temporary-password',
            'role' => UserRole::Writer->value,
            'is_active' => true,
        ])->assertCreated();

        $userId = $created->json('data.id');

        $this->getJson('/admin/api/users')
            ->assertOk()
            ->assertJsonFragment(['email' => 'new@example.com']);

        $this->patchJson("/admin/api/users/{$userId}", [
            'workspace_id' => $workspaceB->id,
            'name' => 'Moved Workspace User',
            'email' => 'new@example.com',
            'role' => UserRole::Admin->value,
            'is_active' => true,
        ])->assertOk()
            ->assertJsonPath('data.workspace_id', $workspaceB->id)
            ->assertJsonPath('data.role', UserRole::Admin->value);

        $this->postJson("/admin/api/users/{$userId}/deactivate")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('user');

        User::factory()->create(['workspace_id' => $workspaceB->id, 'role' => UserRole::Admin]);

        $this->postJson("/admin/api/users/{$userId}/deactivate")
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->postJson('/admin/api/users', [
            'name' => 'Second Super Admin',
            'email' => 'second-super@example.com',
            'password' => 'temporary-password',
            'role' => UserRole::SuperAdmin->value,
            'is_active' => true,
        ])->assertCreated()
            ->assertJsonPath('data.workspace_id', null);
    }

    public function test_super_admin_can_filter_users_by_workspace_with_other_filters(): void
    {
        $workspaceA = Workspace::query()->create(['name' => 'Bough Digital', 'slug' => 'bough']);
        $workspaceB = Workspace::query()->create(['name' => 'Agency Two', 'slug' => 'agency-two']);
        $clientB = Client::query()->create(['workspace_id' => $workspaceB->id, 'name' => 'Client B', 'slug' => 'client-b']);
        $superAdmin = User::factory()->create(['workspace_id' => null, 'role' => UserRole::SuperAdmin, 'name' => 'Platform Owner']);
        $adminA = User::factory()->create(['workspace_id' => $workspaceA->id, 'role' => UserRole::Admin, 'name' => 'Alice Admin', 'email' => 'alice@example.com']);
        $writerB = User::factory()->create(['workspace_id' => $workspaceB->id, 'role' => UserRole::Writer, 'name' => 'Bob Writer', 'email' => 'bob@example.com']);
        $inactiveB = User::factory()->create(['workspace_id' => $workspaceB->id, 'role' => UserRole::Admin, 'name' => 'Beth Admin', 'email' => 'beth@example.com', 'is_active' => false]);
        $multiWorkspaceUser = User::factory()->create(['workspace_id' => $workspaceA->id, 'role' => UserRole::Writer, 'name' => 'Casey Multi', 'email' => 'casey@example.com']);

        DB::table('user_client_access')->insert([
            'workspace_id' => $workspaceB->id,
            'user_id' => $multiWorkspaceUser->id,
            'client_id' => $clientB->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($superAdmin)
            ->getJson('/admin/api/users')
            ->assertOk()
            ->assertJsonPath('meta.total', 5)
            ->assertJsonFragment(['email' => $superAdmin->email])
            ->assertJsonFragment(['email' => $adminA->email])
            ->assertJsonFragment(['email' => $writerB->email]);

        $this->getJson("/admin/api/users?workspace_id={$workspaceA->id}")
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonFragment(['email' => $adminA->email])
            ->assertJsonFragment(['email' => $multiWorkspaceUser->email])
            ->assertJsonMissing(['email' => $writerB->email]);

        // Membership is a single workspace_id; stray client-access rows in
        // another workspace do not make a user a member of it.
        $this->getJson("/admin/api/users?workspace_id={$workspaceB->id}")
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonFragment(['email' => $writerB->email])
            ->assertJsonFragment(['email' => $inactiveB->email])
            ->assertJsonMissing(['email' => $multiWorkspaceUser->email]);

        $this->getJson("/admin/api/users?workspace_id={$workspaceA->id}&search=casey")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.email', $multiWorkspaceUser->email);

        $this->getJson("/admin/api/users?workspace_id={$workspaceB->id}&role=admin&status=inactive")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.email', $inactiveB->email);

        $this->getJson("/admin/api/users?workspace_id={$workspaceB->id}&per_page=1")
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.per_page', 1);
    }

    public function test_workspace_admin_cannot_use_admin_workspace_filter_to_read_other_users(): void
    {
        $workspaceA = Workspace::query()->create(['name' => 'Bough Digital', 'slug' => 'bough']);
        $workspaceB = Workspace::query()->create(['name' => 'Agency Two', 'slug' => 'agency-two']);
        $adminA = User::factory()->create(['workspace_id' => $workspaceA->id, 'role' => UserRole::Admin]);

        $this->actingAs($adminA)
            ->getJson("/admin/api/users?workspace_id={$workspaceB->id}")
            ->assertForbidden();
    }

    public function test_workspace_admin_can_manage_only_own_workspace_users(): void
    {
        $workspaceA = Workspace::query()->create(['name' => 'Bough Digital', 'slug' => 'bough']);
        $workspaceB = Workspace::query()->create(['name' => 'Agency Two', 'slug' => 'agency-two']);
        $adminA = User::factory()->create(['workspace_id' => $workspaceA->id, 'role' => UserRole::Admin]);
        $clientA = Client::query()->create(['workspace_id' => $workspaceA->id, 'name' => 'Client A', 'slug' => 'client-a']);
        $userB = User::factory()->create(['workspace_id' => $workspaceB->id, 'role' => UserRole::Writer]);

        $this->actingAs($adminA);

        $created = $this->postJson("/{$workspaceA->slug}/api/users", [
            'name' => 'Workspace Writer',
            'email' => 'writer@example.com',
            'password' => 'temporary-password',
            'role' => UserRole::Writer->value,
            'is_active' => true,
            'client_ids' => [$clientA->id],
        ])->assertCreated()
            ->assertJsonPath('data.workspace_id', $workspaceA->id)
            ->assertJsonPath('data.role', UserRole::Writer->value);

        $userId = $created->json('data.id');

        $this->getJson("/{$workspaceA->slug}/api/users")
            ->assertOk()
            ->assertJsonFragment(['email' => 'writer@example.com'])
            ->assertJsonMissing(['id' => $userB->id]);

        $this->patchJson("/{$workspaceA->slug}/api/users/{$userId}", [
            'name' => 'Workspace Team Leader',
            'email' => 'writer@example.com',
            'role' => UserRole::TeamLeader->value,
            'is_active' => true,
        ])->assertOk()
            ->assertJsonPath('data.role', UserRole::TeamLeader->value);

        $this->getJson("/{$workspaceA->slug}/api/users/{$userB->id}")
            ->assertNotFound();
    }

    public function test_workspace_admin_cannot_create_or_promote_super_admins_or_move_workspaces(): void
    {
        $workspaceA = Workspace::query()->create(['name' => 'Bough Digital', 'slug' => 'bough']);
        $workspaceB = Workspace::query()->create(['name' => 'Agency Two', 'slug' => 'agency-two']);
        $adminA = User::factory()->create(['workspace_id' => $workspaceA->id, 'role' => UserRole::Admin]);
        $writer = User::factory()->create(['workspace_id' => $workspaceA->id, 'role' => UserRole::Writer]);

        $this->actingAs($adminA);

        $this->postJson("/{$workspaceA->slug}/api/users", [
            'name' => 'Bad Super',
            'email' => 'bad-super@example.com',
            'role' => UserRole::SuperAdmin->value,
        ])->assertForbidden();

        $this->patchJson("/{$workspaceA->slug}/api/users/{$writer->id}", [
            'role' => UserRole::SuperAdmin->value,
        ])->assertForbidden();

        $this->patchJson("/{$workspaceA->slug}/api/users/{$writer->id}", [
            'workspace_id' => $workspaceB->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('workspace_id');
    }

    public function test_super_admin_can_delete_user_while_keeping_owned_records(): void
    {
        $workspace = Workspace::query()->create(['name' => 'Bough Digital', 'slug' => 'bough']);
        $superAdmin = User::factory()->create(['workspace_id' => null, 'role' => UserRole::SuperAdmin]);
        $user = User::factory()->create(['workspace_id' => $workspace->id, 'role' => UserRole::Writer, 'email' => 'remove@example.com']);
        $client = Client::query()->create(['workspace_id' => $workspace->id, 'name' => 'Client A', 'slug' => 'client-a']);

        $document = ClientDocument::query()->create([
            'workspace_id' => $workspace->id,
            'client_id' => $client->id,
            'title' => 'Removal Plan',
            'document_type' => 'brief',
            'created_by' => $user->id,
        ]);

        $task = Task::query()->create([
            'workspace_id' => $workspace->id,
            'client_id' => $client->id,
            'assigned_to' => $user->id,
            'created_by' => $user->id,
            'title' => 'Clean up records',
        ]);

        DB::table('user_client_access')->insert([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'client_id' => $client->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('activity_log')->insert([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'action' => 'test.action',
            'source' => 'test',
            'created_at' => now(),
        ]);

        DB::table('sessions')->insert([
            'id' => 'session-for-removed-user',
            'user_id' => $user->id,
            'payload' => '',
            'last_activity' => now()->timestamp,
        ]);

        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => 'token',
            'created_at' => now(),
        ]);

        $this->actingAs($superAdmin)
            ->deleteJson("/admin/api/users/{$user->id}")
            ->assertOk()
            ->assertJsonPath('message', 'User deleted.');

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseHas('documents', ['id' => $document->id, 'created_by' => null]);
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'assigned_to' => null, 'created_by' => null]);
        $this->assertDatabaseHas('activity_log', ['action' => 'user.deleted', 'workspace_id' => $workspace->id]);
        $this->assertDatabaseMissing('user_client_access', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        $this->assertDatabaseHas('activity_log', ['action' => 'test.action', 'user_id' => null]);
    }

    public function test_user_removal_protects_current_user_and_super_admins(): void
    {
        $superAdmin = User::factory()->create(['workspace_id' => null, 'role' => UserRole::SuperAdmin]);
        $otherSuperAdmin = User::factory()->create(['workspace_id' => null, 'role' => UserRole::SuperAdmin]);

        $this->actingAs($superAdmin)
            ->deleteJson("/admin/api/users/{$superAdmin->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('user');

        $this->deleteJson("/admin/api/users/{$otherSuperAdmin->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('user');

        $this->assertDatabaseHas('users', ['id' => $superAdmin->id]);
        $this->assertDatabaseHas('users', ['id' => $otherSuperAdmin->id]);
    }

    public function test_user_removal_blocks_users_with_time_entries(): void
    {
        $workspace = Workspace::query()->create(['name' => 'Bough Digital', 'slug' => 'bough']);
        $superAdmin = User::factory()->create(['workspace_id' => null, 'role' => UserRole::SuperAdmin]);
        User::factory()->create(['workspace_id' => $workspace->id, 'role' => UserRole::Admin]);
        $user = User::factory()->create(['workspace_id' => $workspace->id, 'role' => UserRole::Writer]);
        $client = Client::query()->create(['workspace_id' => $workspace->id, 'name' => 'Client A', 'slug' => 'client-a']);
        $task = Task::query()->create([
            'workspace_id' => $workspace->id,
            'client_id' => $client->id,
            'title' => 'Tracked task',
        ]);

        DB::table('time_entries')->insert([
            'workspace_id' => $workspace->id,
            'task_id' => $task->id,
            'user_id' => $user->id,
            'hours' => 1.5,
            'worked_on' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($superAdmin)
            ->deleteJson("/admin/api/users/{$user->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('user');

        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseHas('time_entries', ['user_id' => $user->id]);
    }
}
