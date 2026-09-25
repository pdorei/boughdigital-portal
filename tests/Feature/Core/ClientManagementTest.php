<?php

namespace Tests\Feature\Core;

use App\Enums\UserRole;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesWorkspaceFixtures;
use Tests\TestCase;

class ClientManagementTest extends TestCase
{
    use CreatesWorkspaceFixtures, RefreshDatabase;

    public function test_admin_can_create_search_filter_update_and_archive_clients(): void
    {
        $workspace = $this->workspace();
        $admin = $this->member($workspace);

        $this->actingAs($admin);

        $created = $this->postJson('/bough/api/clients', ['name' => 'Acorn Legal', 'website_url' => 'https://acorn.example'])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'acorn-legal')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.can.update', true);

        // A second client with the same name gets a unique slug instead of a database error.
        $this->postJson('/bough/api/clients', ['name' => 'Acorn Legal'])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'acorn-legal-2');

        $this->postJson('/bough/api/clients', ['name' => 'Taken', 'slug' => 'acorn-legal'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('slug');

        $id = $created->json('data.id');

        $this->patchJson("/bough/api/clients/{$id}", ['status' => 'archived'])
            ->assertOk()
            ->assertJsonPath('data.status', 'archived');

        $this->getJson('/bough/api/clients?status=archived')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $id);

        $this->getJson('/bough/api/clients?search=ACORN&per_page=1')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.per_page', 1);

        $this->assertDatabaseHas('activity_log', ['action' => 'client.created', 'client_id' => $id]);
        $this->assertDatabaseHas('activity_log', ['action' => 'client.archived', 'client_id' => $id]);
    }

    public function test_clients_with_history_must_be_archived_rather_than_deleted(): void
    {
        $workspace = $this->workspace();
        $admin = $this->member($workspace);
        $withTasks = $this->client($workspace, 'busy-client');
        $empty = $this->client($workspace, 'empty-client');
        Task::query()->create(['workspace_id' => $workspace->id, 'client_id' => $withTasks->id, 'title' => 'Audit']);

        $this->actingAs($admin)
            ->deleteJson("/bough/api/clients/{$withTasks->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('client');

        $this->deleteJson("/bough/api/clients/{$empty->id}")->assertNoContent();

        $this->assertDatabaseHas('clients', ['id' => $withTasks->id]);
        $this->assertDatabaseMissing('clients', ['id' => $empty->id]);
        $this->assertDatabaseHas('activity_log', ['action' => 'client.deleted', 'workspace_id' => $workspace->id]);
    }

    public function test_client_validation_errors_are_returned(): void
    {
        $workspace = $this->workspace();

        $this->actingAs($this->member($workspace))
            ->postJson('/bough/api/clients', ['name' => '', 'website_url' => 'not-a-url', 'status' => 'deleted'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'website_url', 'status']);
    }

    public function test_restricted_users_only_reach_clients_they_were_given(): void
    {
        $workspace = $this->workspace();
        $allowed = $this->client($workspace, 'allowed-client');
        $hidden = $this->client($workspace, 'hidden-client');
        $writer = $this->member($workspace, UserRole::Writer);
        $this->grantClientAccess($writer, $allowed);

        $this->actingAs($writer);

        $this->get('/bough/clients/allowed-client')->assertOk();
        $this->get('/bough/clients/hidden-client')->assertForbidden();
        $this->getJson("/bough/api/clients/{$hidden->id}")->assertForbidden();
        $this->getJson('/bough/api/clients')->assertOk()->assertJsonPath('meta.total', 1);
        $this->postJson('/bough/api/clients', ['name' => 'Nope'])->assertForbidden();
        $this->patchJson("/bough/api/clients/{$allowed->id}", ['name' => 'Renamed'])->assertForbidden();
        $this->get('/bough/clients/allowed-client/edit')->assertForbidden();
    }

    public function test_clients_from_another_workspace_are_not_found(): void
    {
        $workspace = $this->workspace();
        $other = $this->workspace('agency-two');
        $foreign = $this->client($other, 'foreign-client');

        $this->actingAs($this->member($workspace));

        $this->getJson("/bough/api/clients/{$foreign->id}")->assertNotFound();
        $this->patchJson("/bough/api/clients/{$foreign->id}", ['name' => 'Hijacked'])->assertNotFound();
        $this->deleteJson("/bough/api/clients/{$foreign->id}")->assertNotFound();
        $this->get('/bough/clients/foreign-client')->assertNotFound();

        $this->assertDatabaseHas('clients', ['id' => $foreign->id, 'name' => 'Foreign Client']);
    }
}
