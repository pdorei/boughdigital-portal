<?php

namespace Tests\Feature\Core;

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\ContentCalendarEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesWorkspaceFixtures;
use Tests\TestCase;

class ContentCalendarTest extends TestCase
{
    use CreatesWorkspaceFixtures, RefreshDatabase;

    public function test_admin_can_plan_filter_update_and_delete_calendar_entries(): void
    {
        $workspace = $this->workspace();
        $client = $this->client($workspace);
        $admin = $this->member($workspace);
        $writer = $this->member($workspace, UserRole::Writer, ['name' => 'John']);
        $this->grantClientAccess($writer, $client);
        $pillar = $client->contentPillars()->create(['workspace_id' => $workspace->id, 'name' => 'Accounting']);
        $this->actingAs($admin);

        $id = $this->postJson($this->url($client), [
            'title' => 'Best Accounting Software for Small Businesses',
            'target_keyword' => 'best accounting software',
            'secondary_keywords' => ['accounting apps', ' Accounting Apps ', 'xero vs myob'],
            'content_pillar_id' => $pillar->id,
            'assigned_writer_id' => $writer->id,
            'search_intent' => 'commercial',
            'publish_date' => '2026-09-28',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'planned')
            ->assertJsonPath('data.content_type', 'article')
            ->assertJsonPath('data.secondary_keywords', ['accounting apps', 'xero vs myob'])
            ->assertJsonPath('data.writer.name', 'John')
            ->assertJsonPath('data.pillar.name', 'Accounting')
            ->json('data.id');

        $this->postJson($this->url($client), ['title' => 'How to Reduce Business Costs', 'publish_date' => '2026-10-03'])->assertCreated();
        $this->postJson($this->url($client), ['title' => 'Unscheduled idea'])->assertCreated();

        $this->getJson($this->url($client))->assertOk()
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('data.0.publish_date', '2026-09-28')
            ->assertJsonPath('data.2.publish_date', null);
        $this->getJson($this->url($client).'?search=accounting')->assertJsonPath('meta.total', 1);
        $this->getJson($this->url($client)."?writer={$writer->id}")->assertJsonPath('meta.total', 1);
        $this->getJson($this->url($client)."?pillar={$pillar->id}")->assertJsonPath('meta.total', 1);
        $this->getJson($this->url($client).'?from=2026-10-01&to=2026-10-31')->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.title', 'How to Reduce Business Costs');

        $this->patchJson($this->url($client, $id), ['status' => 'writing'])->assertOk()->assertJsonPath('data.status', 'writing');
        $this->getJson($this->url($client).'?status=writing')->assertJsonPath('meta.total', 1);

        $this->deleteJson($this->url($client, $id))->assertNoContent();
        $this->assertDatabaseMissing('content_calendar_entries', ['id' => $id]);

        foreach (['content_calendar.created', 'content_calendar.updated', 'content_calendar.deleted'] as $event) {
            $this->assertDatabaseHas('activity_log', ['action' => $event, 'entity_id' => $id, 'client_id' => $client->id]);
        }
    }

    public function test_entries_are_validated_and_can_only_reference_the_clients_own_records(): void
    {
        $workspace = $this->workspace();
        $client = $this->client($workspace);
        $sibling = $this->client($workspace, 'birch-dental');
        $siblingPillar = $sibling->contentPillars()->create(['workspace_id' => $workspace->id, 'name' => 'Dental']);
        $siblingArticle = $sibling->articles()->create(['workspace_id' => $workspace->id, 'title' => 'Teeth']);
        $noAccessWriter = $this->member($workspace, UserRole::Writer);
        $outsider = $this->member($this->workspace('agency-two'), UserRole::Admin);
        $this->actingAs($this->member($workspace));

        $this->postJson($this->url($client), [])->assertUnprocessable()->assertJsonValidationErrors('title');
        $this->postJson($this->url($client), ['title' => 'x', 'status' => 'done', 'content_type' => 'podcast', 'search_intent' => 'curious', 'publish_date' => '28/09/2026'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status', 'content_type', 'search_intent', 'publish_date']);

        $this->postJson($this->url($client), ['title' => 'x', 'content_pillar_id' => $siblingPillar->id])->assertUnprocessable()->assertJsonValidationErrors('content_pillar_id');
        $this->postJson($this->url($client), ['title' => 'x', 'article_id' => $siblingArticle->id])->assertUnprocessable()->assertJsonValidationErrors('article_id');
        $this->postJson($this->url($client), ['title' => 'x', 'assigned_writer_id' => $noAccessWriter->id])->assertUnprocessable()->assertJsonValidationErrors('assigned_writer_id');
        $this->postJson($this->url($client), ['title' => 'x', 'assigned_writer_id' => $outsider->id])->assertUnprocessable()->assertJsonValidationErrors('assigned_writer_id');

        $article = $client->articles()->create(['workspace_id' => $workspace->id, 'title' => 'Mine']);
        $this->postJson($this->url($client), ['title' => 'First', 'article_id' => $article->id])->assertCreated();
        $this->postJson($this->url($client), ['title' => 'Second', 'article_id' => $article->id])->assertUnprocessable()->assertJsonValidationErrors('article_id');

        $this->assertSame(1, ContentCalendarEntry::query()->count());
    }

    public function test_calendar_permissions_follow_client_access_and_role(): void
    {
        $workspace = $this->workspace();
        $client = $this->client($workspace);
        $entry = $this->entry($client);

        $this->actingAs($this->member($workspace, UserRole::Employee))->getJson($this->url($client))->assertForbidden();

        $writer = $this->member($workspace, UserRole::Writer);
        $this->grantClientAccess($writer, $client);
        $this->actingAs($writer)->getJson($this->url($client))->assertOk()->assertJsonPath('meta.can_create', false);
        $this->getJson($this->url($client, $entry->id))->assertOk();
        $this->postJson($this->url($client), ['title' => 'Idea'])->assertForbidden();
        $this->patchJson($this->url($client, $entry->id), ['status' => 'writing'])->assertForbidden();

        $leader = $this->member($workspace, UserRole::TeamLeader);
        $this->grantClientAccess($leader, $client);
        $this->actingAs($leader)->patchJson($this->url($client, $entry->id), ['status' => 'writing'])->assertOk();
        $this->deleteJson($this->url($client, $entry->id))->assertForbidden();
    }

    public function test_calendar_entries_are_isolated_between_workspaces_and_clients(): void
    {
        $workspace = $this->workspace();
        $client = $this->client($workspace);
        $sibling = $this->client($workspace, 'birch-dental');
        $entry = $this->entry($client);

        $other = $this->workspace('agency-two');
        $foreign = "/{$other->slug}/api/clients/{$client->id}/content-calendar";
        $this->actingAs($this->member($other));

        $this->getJson($foreign)->assertNotFound();
        $this->getJson("{$foreign}/{$entry->id}")->assertNotFound();
        $this->postJson($foreign, ['title' => 'Planted'])->assertNotFound();
        $this->patchJson("{$foreign}/{$entry->id}", ['title' => 'Hijacked'])->assertNotFound();
        $this->deleteJson("{$foreign}/{$entry->id}")->assertNotFound();
        $this->getJson($this->url($client, $entry->id))->assertForbidden();

        $this->actingAs($this->member($workspace))->deleteJson($this->url($sibling, $entry->id))->assertNotFound();

        $this->assertSame('Tax deadlines', $entry->refresh()->title);
    }

    private function url(Client $client, ?int $id = null): string
    {
        return "/{$client->workspace->slug}/api/clients/{$client->id}/content-calendar".($id ? "/{$id}" : '');
    }

    private function entry(Client $client): ContentCalendarEntry
    {
        return $client->calendarEntries()->create(['workspace_id' => $client->workspace_id, 'title' => 'Tax deadlines']);
    }
}
