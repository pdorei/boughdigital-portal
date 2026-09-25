<?php

namespace Tests\Feature\Core;

use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesWorkspaceFixtures;
use Tests\TestCase;

class TaskManagementTest extends TestCase
{
    use CreatesWorkspaceFixtures, RefreshDatabase;

    public function test_team_leader_can_create_assign_update_and_filter_tasks(): void
    {
        $workspace = $this->workspace();
        $client = $this->client($workspace);
        $leader = $this->member($workspace, UserRole::TeamLeader);
        $writer = $this->member($workspace, UserRole::Writer);
        $this->grantClientAccess($leader, $client);

        $this->actingAs($leader);

        $created = $this->postJson('/bough/api/tasks', [
            'title' => 'Write landing page brief',
            'client_id' => $client->id,
            'assigned_to' => $writer->id,
            'priority' => 'high',
            'due_date' => '2026-10-01',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'backlog')
            ->assertJsonPath('data.due_date', '2026-10-01')
            ->assertJsonPath('data.assignee.id', $writer->id)
            ->assertJsonPath('data.client.id', $client->id);

        $id = $created->json('data.id');

        $this->patchJson("/bough/api/tasks/{$id}", ['assigned_to' => $leader->id, 'status' => 'in_progress', 'title' => 'Write brief'])
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress');

        $this->getJson("/bough/api/tasks?status=in_progress&priority=high&client_id={$client->id}&search=brief")
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->getJson('/bough/api/tasks?assigned_to=me&open=1')->assertOk()->assertJsonPath('meta.total', 1);

        foreach (['task.created', 'task.assigned', 'task.status_changed', 'task.updated'] as $event) {
            $this->assertDatabaseHas('activity_log', ['action' => $event, 'entity_id' => $id]);
        }
    }

    public function test_tasks_cannot_point_at_users_or_clients_from_another_workspace(): void
    {
        $workspace = $this->workspace();
        $other = $this->workspace('agency-two');
        $admin = $this->member($workspace);
        $outsider = $this->member($other, UserRole::Employee);
        $inactive = $this->member($workspace, UserRole::Employee, ['is_active' => false]);
        $foreignClient = $this->client($other, 'foreign-client');

        $this->actingAs($admin);

        $this->postJson('/bough/api/tasks', ['title' => 'Bad assignee', 'assigned_to' => $outsider->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('assigned_to');

        $this->postJson('/bough/api/tasks', ['title' => 'Inactive assignee', 'assigned_to' => $inactive->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('assigned_to');

        $this->postJson('/bough/api/tasks', ['title' => 'Bad client', 'client_id' => $foreignClient->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('client_id');

        $task = Task::query()->create(['workspace_id' => $workspace->id, 'title' => 'Existing']);

        $this->patchJson("/bough/api/tasks/{$task->id}", ['assigned_to' => $outsider->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('assigned_to');

        $this->assertNull($task->refresh()->assigned_to);
    }

    public function test_assignees_can_move_their_task_but_not_edit_or_delete_it(): void
    {
        $workspace = $this->workspace();
        $employee = $this->member($workspace, UserRole::Employee);
        $task = Task::query()->create(['workspace_id' => $workspace->id, 'title' => 'Assigned', 'assigned_to' => $employee->id]);
        $someoneElses = Task::query()->create(['workspace_id' => $workspace->id, 'title' => 'Not mine']);

        $this->actingAs($employee);

        $this->patchJson("/bough/api/tasks/{$task->id}/status", ['status' => 'completed'])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $this->patchJson("/bough/api/tasks/{$task->id}", ['title' => 'Renamed'])->assertForbidden();
        $this->deleteJson("/bough/api/tasks/{$task->id}")->assertForbidden();
        $this->postJson('/bough/api/tasks', ['title' => 'New'])->assertForbidden();
        $this->getJson("/bough/api/tasks/{$someoneElses->id}")->assertForbidden();
        $this->patchJson("/bough/api/tasks/{$someoneElses->id}/status", ['status' => 'completed'])->assertForbidden();

        $this->getJson('/bough/api/tasks')->assertOk()->assertJsonPath('meta.total', 1);
        $this->assertSame(TaskStatus::Completed, $task->refresh()->status);
    }

    public function test_tasks_with_logged_time_cannot_be_deleted(): void
    {
        $workspace = $this->workspace();
        $admin = $this->member($workspace);
        $tracked = Task::query()->create(['workspace_id' => $workspace->id, 'title' => 'Tracked']);
        $plain = Task::query()->create(['workspace_id' => $workspace->id, 'title' => 'Plain']);
        DB::table('time_entries')->insert([
            'workspace_id' => $workspace->id, 'task_id' => $tracked->id, 'user_id' => $admin->id,
            'hours' => 1, 'worked_on' => '2026-09-01', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($admin);

        $this->deleteJson("/bough/api/tasks/{$tracked->id}")->assertUnprocessable()->assertJsonValidationErrors('task');
        $this->deleteJson("/bough/api/tasks/{$plain->id}")->assertNoContent();

        $this->assertDatabaseHas('tasks', ['id' => $tracked->id]);
        $this->assertDatabaseHas('activity_log', ['action' => 'task.deleted', 'entity_id' => $plain->id]);
    }

    public function test_tasks_from_another_workspace_are_not_found(): void
    {
        $workspace = $this->workspace();
        $other = $this->workspace('agency-two');
        $foreign = Task::query()->create(['workspace_id' => $other->id, 'title' => 'Foreign']);

        $this->actingAs($this->member($workspace));

        $this->getJson("/bough/api/tasks/{$foreign->id}")->assertNotFound();
        $this->patchJson("/bough/api/tasks/{$foreign->id}", ['title' => 'Hijacked'])->assertNotFound();
        $this->patchJson("/bough/api/tasks/{$foreign->id}/status", ['status' => 'completed'])->assertNotFound();
        $this->deleteJson("/bough/api/tasks/{$foreign->id}")->assertNotFound();
    }
}
