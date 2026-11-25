<?php

namespace Tests\Feature\Core;

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\ContentPillar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesWorkspaceFixtures;
use Tests\TestCase;

class ContentPillarTest extends TestCase
{
    use CreatesWorkspaceFixtures, RefreshDatabase;

    public function test_admin_can_create_update_deactivate_and_delete_pillars(): void
    {
        $workspace = $this->workspace();
        $client = $this->client($workspace);
        $this->actingAs($this->member($workspace));

        $id = $this->postJson($this->url($client), ['name' => 'Accounting', 'color' => '#8fcf00', 'description' => 'Bookkeeping and accounts'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Accounting')
            ->assertJsonPath('data.active', true)
            ->assertJsonPath('data.sort_order', 1)
            ->json('data.id');

        $this->postJson($this->url($client), ['name' => 'Tax'])->assertCreated()->assertJsonPath('data.sort_order', 2);

        $this->patchJson($this->url($client, $id), ['name' => 'Accounting & Bookkeeping', 'active' => false])
            ->assertOk()
            ->assertJsonPath('data.name', 'Accounting & Bookkeeping')
            ->assertJsonPath('data.active', false);

        $this->getJson($this->url($client))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.articles_count', 0)
            ->assertJsonPath('meta.can_create', true);

        $this->deleteJson($this->url($client, $id))->assertNoContent();
        $this->assertDatabaseMissing('content_pillars', ['id' => $id]);

        foreach (['content_pillar.created', 'content_pillar.updated', 'content_pillar.deleted'] as $event) {
            $this->assertDatabaseHas('activity_log', ['action' => $event, 'entity_id' => $id, 'client_id' => $client->id]);
        }
    }

    public function test_pillar_names_are_required_and_unique_per_client(): void
    {
        $workspace = $this->workspace();
        $client = $this->client($workspace);
        $other = $this->client($workspace, 'birch-dental');
        $this->actingAs($this->member($workspace));

        $this->postJson($this->url($client), [])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->postJson($this->url($client), ['name' => 'Tax', 'color' => 'green'])->assertUnprocessable()->assertJsonValidationErrors('color');

        $this->postJson($this->url($client), ['name' => 'Tax'])->assertCreated();
        $this->postJson($this->url($client), ['name' => ' tax '])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->postJson($this->url($other), ['name' => 'Tax'])->assertCreated();
    }

    public function test_a_pillar_in_use_cannot_be_deleted(): void
    {
        $workspace = $this->workspace();
        $client = $this->client($workspace);
        $this->actingAs($this->member($workspace));

        $pillar = $this->pillar($client);
        $article = $client->articles()->create(['workspace_id' => $workspace->id, 'title' => 'Tax tips', 'content_pillar_id' => $pillar->id]);
        $client->calendarEntries()->create(['workspace_id' => $workspace->id, 'title' => 'Tax deadlines', 'content_pillar_id' => $pillar->id]);

        $this->deleteJson($this->url($client, $pillar->id))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('pillar');

        $this->assertDatabaseHas('content_pillars', ['id' => $pillar->id]);
        $this->assertSame($pillar->id, $article->refresh()->content_pillar_id);
    }

    public function test_pillar_permissions_follow_client_access_and_role(): void
    {
        $workspace = $this->workspace();
        $client = $this->client($workspace);
        $pillar = $this->pillar($client);

        $this->actingAs($this->member($workspace, UserRole::Writer))->getJson($this->url($client))->assertForbidden();

        $writer = $this->member($workspace, UserRole::Writer);
        $this->grantClientAccess($writer, $client);
        $this->actingAs($writer)->getJson($this->url($client))->assertOk()->assertJsonPath('meta.can_create', false)->assertJsonPath('data.0.can.update', false);
        $this->postJson($this->url($client), ['name' => 'Growth'])->assertForbidden();
        $this->patchJson($this->url($client, $pillar->id), ['name' => 'Changed'])->assertForbidden();

        $leader = $this->member($workspace, UserRole::TeamLeader);
        $this->grantClientAccess($leader, $client);
        $this->actingAs($leader)->postJson($this->url($client), ['name' => 'Growth'])->assertCreated();
        $this->patchJson($this->url($client, $pillar->id), ['active' => false])->assertOk();
        $this->deleteJson($this->url($client, $pillar->id))->assertForbidden();
    }

    public function test_pillars_are_isolated_between_workspaces_and_clients(): void
    {
        $workspace = $this->workspace();
        $client = $this->client($workspace);
        $sibling = $this->client($workspace, 'birch-dental');
        $pillar = $this->pillar($client);

        $other = $this->workspace('agency-two');
        $foreign = "/{$other->slug}/api/clients/{$client->id}/content-pillars";
        $this->actingAs($this->member($other));

        $this->getJson($foreign)->assertNotFound();
        $this->getJson("{$foreign}/{$pillar->id}")->assertNotFound();
        $this->postJson($foreign, ['name' => 'Planted'])->assertNotFound();
        $this->patchJson("{$foreign}/{$pillar->id}", ['name' => 'Hijacked'])->assertNotFound();
        $this->deleteJson("{$foreign}/{$pillar->id}")->assertNotFound();
        $this->getJson($this->url($client))->assertForbidden();

        $this->actingAs($this->member($workspace))->patchJson($this->url($sibling, $pillar->id), ['name' => 'Moved'])->assertNotFound();

        $this->assertSame('Tax', $pillar->refresh()->name);
        $this->assertSame(1, ContentPillar::query()->count());
    }

    private function url(Client $client, ?int $id = null): string
    {
        return "/{$client->workspace->slug}/api/clients/{$client->id}/content-pillars".($id ? "/{$id}" : '');
    }

    private function pillar(Client $client): ContentPillar
    {
        return $client->contentPillars()->create(['workspace_id' => $client->workspace_id, 'name' => 'Tax']);
    }
}
