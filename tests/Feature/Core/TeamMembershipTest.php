<?php

namespace Tests\Feature\Core;

use App\Enums\UserRole;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesWorkspaceFixtures;
use Tests\TestCase;

class TeamMembershipTest extends TestCase
{
    use CreatesWorkspaceFixtures, RefreshDatabase;

    public function test_removing_a_member_keeps_the_account_and_its_history(): void
    {
        $workspace = $this->workspace();
        $client = $this->client($workspace);
        $admin = $this->member($workspace);
        $writer = $this->member($workspace, UserRole::Writer);
        $this->grantClientAccess($writer, $client);
        $task = Task::query()->create(['workspace_id' => $workspace->id, 'title' => 'Draft', 'assigned_to' => $writer->id]);
        $writer->createToken('mcp');

        $this->actingAs($admin)
            ->deleteJson("/bough/api/users/{$writer->id}/membership")
            ->assertOk()
            ->assertJsonPath('data.workspace_id', null);

        $this->assertDatabaseHas('users', ['id' => $writer->id, 'workspace_id' => null]);
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'assigned_to' => $writer->id]);
        $this->assertDatabaseMissing('user_client_access', ['user_id' => $writer->id]);
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $writer->id]);
        $this->assertDatabaseHas('activity_log', ['action' => 'workspace.member_removed', 'workspace_id' => $workspace->id, 'entity_id' => $writer->id]);

        $this->getJson("/bough/api/users/{$writer->id}")->assertNotFound();
    }

    public function test_admins_cannot_remove_or_demote_themselves_and_the_last_admin_is_kept(): void
    {
        $workspace = $this->workspace();
        $admin = $this->member($workspace);

        $this->actingAs($admin);

        $this->deleteJson("/bough/api/users/{$admin->id}/membership")->assertForbidden();
        $this->postJson("/bough/api/users/{$admin->id}/deactivate")->assertForbidden();
        $this->patchJson("/bough/api/users/{$admin->id}", ['role' => 'writer'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('user');

        $secondAdmin = $this->member($workspace);
        $this->patchJson("/bough/api/users/{$secondAdmin->id}", ['role' => 'employee'])->assertOk();
        $this->assertDatabaseHas('activity_log', ['action' => 'workspace.member_role_changed', 'entity_id' => $secondAdmin->id]);
        $this->assertSame(UserRole::Admin, $admin->refresh()->role);
    }

    public function test_workspace_admins_cannot_change_a_fellow_admins_credentials(): void
    {
        $workspace = $this->workspace();
        $admin = $this->member($workspace);
        $peer = $this->member($workspace, UserRole::Admin, ['email' => 'peer@example.com']);
        $writer = $this->member($workspace, UserRole::Writer);

        $this->actingAs($admin);

        $this->patchJson("/bough/api/users/{$peer->id}", ['email' => 'taken-over@example.com'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->patchJson("/bough/api/users/{$peer->id}", ['password' => 'a-new-password-123'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');

        $this->patchJson("/bough/api/users/{$peer->id}", ['name' => 'Peer Renamed', 'email' => 'peer@example.com'])->assertOk();
        $this->patchJson("/bough/api/users/{$writer->id}", ['email' => 'writer-new@example.com'])->assertOk();
    }

    public function test_members_of_another_workspace_cannot_be_manipulated(): void
    {
        $workspace = $this->workspace();
        $other = $this->workspace('agency-two');
        $admin = $this->member($workspace);
        $outsider = $this->member($other, UserRole::Writer);

        $this->actingAs($admin);

        $this->patchJson("/bough/api/users/{$outsider->id}", ['name' => 'Hijacked'])->assertForbidden();
        $this->postJson("/bough/api/users/{$outsider->id}/deactivate")->assertNotFound();
        $this->deleteJson("/bough/api/users/{$outsider->id}/membership")->assertNotFound();
        $this->get("/bough/team/{$outsider->id}")->assertNotFound();

        $this->assertDatabaseHas('users', ['id' => $outsider->id, 'name' => $outsider->name, 'workspace_id' => $other->id, 'is_active' => true]);
    }

    public function test_team_pages_are_limited_to_workspace_admins(): void
    {
        $workspace = $this->workspace();

        $this->actingAs($this->member($workspace, UserRole::TeamLeader));

        $this->get('/bough/team')->assertForbidden();
        $this->get('/bough/team/create')->assertForbidden();
        $this->getJson('/bough/api/users')->assertForbidden();
    }
}
