<?php

namespace Tests\Feature\Core;

use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\Article;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesWorkspaceFixtures;
use Tests\TestCase;

class ArticleTest extends TestCase
{
    use CreatesWorkspaceFixtures, RefreshDatabase;

    public function test_admin_can_create_update_and_delete_an_article_with_its_pillar(): void
    {
        $workspace = $this->workspace();
        $client = $this->client($workspace);
        $writer = $this->member($workspace, UserRole::Writer, ['name' => 'Sarah']);
        $this->grantClientAccess($writer, $client);
        $pillar = $client->contentPillars()->create(['workspace_id' => $workspace->id, 'name' => 'Accounting']);
        $this->actingAs($this->member($workspace));

        $body = "Choosing accounting software is hard.\n\nHere are five good options for small businesses.";

        $created = $this->postJson($this->url($client), [
            'title' => 'Best Accounting Software for Small Businesses',
            'slug' => 'Best Accounting Software!',
            'content_pillar_id' => $pillar->id,
            'assigned_writer_id' => $writer->id,
            'primary_keyword' => 'best accounting software',
            'secondary_keywords' => ['xero', 'myob'],
            'search_intent' => 'commercial',
            'meta_title' => 'Best Accounting Software (2026)',
            'canonical_url' => 'https://acorn.example/best-accounting-software',
            'content_body' => $body,
            'word_count' => 3,
            'tags' => ['software', 'Software', 'guides'],
        ])->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.slug', 'best-accounting-software')
            ->assertJsonPath('data.word_count', 13)
            ->assertJsonPath('data.tags', ['software', 'guides'])
            ->assertJsonPath('data.pillar.name', 'Accounting')
            ->assertJsonPath('data.writer.name', 'Sarah')
            ->assertJsonPath('data.content_body', $body);
        $id = $created->json('data.id');

        // Listings leave the body out.
        $this->getJson($this->url($client))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonMissingPath('data.0.content_body')
            ->assertJsonPath('data.0.latest_ai_score', null);
        $this->getJson($this->url($client)."?pillar={$pillar->id}&search=accounting")->assertJsonPath('meta.total', 1);

        $this->patchJson($this->url($client, $id), ['status' => 'published', 'published_url' => 'https://acorn.example/blog/software'])
            ->assertOk()
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.published_url', 'https://acorn.example/blog/software');
        $this->assertNotNull(Article::query()->findOrFail($id)->published_at);

        $this->deleteJson($this->url($client, $id))->assertNoContent();
        $this->assertDatabaseMissing('articles', ['id' => $id]);

        foreach (['article.created', 'article.updated', 'article.deleted'] as $event) {
            $this->assertDatabaseHas('activity_log', ['action' => $event, 'entity_id' => $id, 'client_id' => $client->id]);
        }
        $this->assertStringNotContainsString('Choosing accounting software', ActivityLog::query()->where('entity_id', $id)->pluck('details')->toJson());
    }

    public function test_article_can_be_created_from_a_calendar_entry_and_deleting_it_keeps_the_plan(): void
    {
        $workspace = $this->workspace();
        $client = $this->client($workspace);
        $entry = $client->calendarEntries()->create(['workspace_id' => $workspace->id, 'title' => 'Best Accounting Software']);
        $this->actingAs($this->member($workspace));

        $id = $this->postJson($this->url($client), ['title' => 'Best Accounting Software for Small Businesses', 'calendar_entry_id' => $entry->id])
            ->assertCreated()
            ->assertJsonPath('data.calendar_entry.id', $entry->id)
            ->json('data.id');
        $this->assertSame($id, $entry->refresh()->article_id);

        $this->postJson($this->url($client), ['title' => 'Duplicate', 'calendar_entry_id' => $entry->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('calendar_entry_id');

        $this->deleteJson($this->url($client, $id))->assertNoContent();
        $this->assertDatabaseHas('content_calendar_entries', ['id' => $entry->id, 'article_id' => null]);
    }

    public function test_article_fields_are_validated(): void
    {
        $workspace = $this->workspace();
        $client = $this->client($workspace);
        $sibling = $this->client($workspace, 'birch-dental');
        $siblingPillar = $sibling->contentPillars()->create(['workspace_id' => $workspace->id, 'name' => 'Dental']);
        $siblingEntry = $sibling->calendarEntries()->create(['workspace_id' => $workspace->id, 'title' => 'Teeth']);
        $this->actingAs($this->member($workspace));

        $this->postJson($this->url($client), [])->assertUnprocessable()->assertJsonValidationErrors('title');
        $this->postJson($this->url($client), ['title' => 'x', 'status' => 'live', 'canonical_url' => 'javascript:alert(1)', 'published_url' => 'not a url', 'tags' => 'seo'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status', 'canonical_url', 'published_url', 'tags']);
        $this->postJson($this->url($client), ['title' => 'x', 'content_pillar_id' => $siblingPillar->id])->assertUnprocessable()->assertJsonValidationErrors('content_pillar_id');
        $this->postJson($this->url($client), ['title' => 'x', 'calendar_entry_id' => $siblingEntry->id])->assertUnprocessable()->assertJsonValidationErrors('calendar_entry_id');

        $this->assertSame(0, Article::query()->count());
    }

    public function test_scores_keep_history_and_category_breakdown(): void
    {
        $workspace = $this->workspace();
        $client = $this->client($workspace);
        $article = $this->article($client);
        $this->actingAs($this->member($workspace, attributes: ['name' => 'Reviewer']));

        $this->travelTo(now()->setDate(2026, 9, 20));
        $this->postJson($this->scoresUrl($article), ['score_type' => 'ai', 'overall_score' => 84])->assertCreated();
        $this->travelTo(now()->setDate(2026, 9, 21));
        $this->postJson($this->scoresUrl($article), ['score_type' => 'human', 'overall_score' => 89])->assertCreated();
        $this->travelTo(now()->setDate(2026, 9, 25));
        $ai = $this->postJson($this->scoresUrl($article), [
            'score_type' => 'ai',
            'overall_score' => 87,
            'category_scores' => ['seo' => 90, 'readability' => 85, 'brand_voice' => 92, 'structure' => 88, 'originality' => 84],
            'feedback' => 'Strong structure; tighten the introduction.',
        ])->assertCreated()
            ->assertJsonPath('data.category_scores', ['seo' => 90, 'readability' => 85, 'brand_voice' => 92, 'structure' => 88, 'originality' => 84])
            ->assertJsonPath('data.scorer.name', 'Reviewer');
        $this->travelTo(now()->setDate(2026, 9, 26));
        $this->postJson($this->scoresUrl($article), ['score_type' => 'human', 'overall_score' => 92, 'category_scores' => ['seo' => 95]])
            ->assertCreated()
            ->assertJsonPath('data.category_scores.seo', 95)
            ->assertJsonPath('data.category_scores.readability', null);

        $this->getJson($this->scoresUrl($article))
            ->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonPath('data.0.overall_score', 92)
            ->assertJsonPath('data.3.overall_score', 84);

        $this->getJson($this->scoresUrl($article).'/'.$ai->json('data.id'))->assertOk()->assertJsonPath('data.feedback', 'Strong structure; tighten the introduction.');

        $this->getJson($this->url($client, $article->id))
            ->assertJsonPath('data.latest_ai_score.overall_score', 87)
            ->assertJsonPath('data.latest_human_score.overall_score', 92);

        $this->assertSame(4, ActivityLog::query()->where('action', 'article.scored')->where('entity_id', $article->id)->count());
    }

    public function test_scores_are_validated(): void
    {
        $workspace = $this->workspace();
        $article = $this->article($this->client($workspace));
        $this->actingAs($this->member($workspace));

        $this->postJson($this->scoresUrl($article), [])->assertUnprocessable()->assertJsonValidationErrors(['score_type', 'overall_score']);
        $this->postJson($this->scoresUrl($article), ['score_type' => 'robot', 'overall_score' => 101, 'category_scores' => ['seo' => -1, 'humour' => 50]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['score_type', 'overall_score', 'category_scores', 'category_scores.seo']);
    }

    public function test_writers_edit_their_own_articles_but_cannot_score_or_delete(): void
    {
        $workspace = $this->workspace();
        $client = $this->client($workspace);
        $writer = $this->member($workspace, UserRole::Writer);
        $this->grantClientAccess($writer, $client);
        $own = $this->article($client, ['assigned_writer_id' => $writer->id]);
        $others = $this->article($client);

        $this->actingAs($writer);
        $this->getJson($this->url($client))->assertOk()->assertJsonPath('meta.can_create', false);
        $this->postJson($this->url($client), ['title' => 'New'])->assertForbidden();
        $this->patchJson($this->url($client, $own->id), ['content_body' => 'First draft'])->assertOk()->assertJsonPath('data.word_count', 2);
        $this->patchJson($this->url($client, $others->id), ['content_body' => 'Not mine'])->assertForbidden();
        $this->postJson($this->scoresUrl($own), ['score_type' => 'human', 'overall_score' => 99])->assertForbidden();
        $this->getJson($this->scoresUrl($own))->assertOk();
        $this->deleteJson($this->url($client, $own->id))->assertForbidden();

        $this->actingAs($this->member($workspace, UserRole::Writer))->getJson($this->url($client, $own->id))->assertForbidden();
    }

    public function test_articles_and_scores_are_isolated_between_workspaces_and_clients(): void
    {
        $workspace = $this->workspace();
        $client = $this->client($workspace);
        $sibling = $this->client($workspace, 'birch-dental');
        $article = $this->article($client);
        $article->scores()->create(['workspace_id' => $workspace->id, 'score_type' => 'ai', 'overall_score' => 70]);

        $other = $this->workspace('agency-two');
        $foreign = "/{$other->slug}/api/clients/{$client->id}/articles";
        $this->actingAs($this->member($other));

        $this->getJson($foreign)->assertNotFound();
        $this->getJson("{$foreign}/{$article->id}")->assertNotFound();
        $this->postJson($foreign, ['title' => 'Planted'])->assertNotFound();
        $this->patchJson("{$foreign}/{$article->id}", ['title' => 'Hijacked'])->assertNotFound();
        $this->deleteJson("{$foreign}/{$article->id}")->assertNotFound();
        $this->getJson("{$foreign}/{$article->id}/scores")->assertNotFound();
        $this->postJson("{$foreign}/{$article->id}/scores", ['score_type' => 'human', 'overall_score' => 1])->assertNotFound();
        $this->getJson($this->url($client, $article->id))->assertForbidden();
        $this->get("/{$workspace->slug}/clients/{$client->slug}/articles/{$article->id}")->assertForbidden();

        $this->actingAs($this->member($workspace));
        $this->getJson($this->url($sibling, $article->id))->assertNotFound();
        $this->get("/{$workspace->slug}/clients/{$sibling->slug}/articles/{$article->id}")->assertNotFound();

        $this->assertSame('Draft article', $article->refresh()->title);
        $this->assertSame(1, $article->scores()->count());
    }

    public function test_article_pages_render_for_authorized_users(): void
    {
        $workspace = $this->workspace();
        $client = $this->client($workspace);
        $article = $this->article($client);
        $entry = $client->calendarEntries()->create(['workspace_id' => $workspace->id, 'title' => 'Planned piece', 'target_keyword' => 'planned']);
        $this->actingAs($this->member($workspace));

        $this->get("/bough/clients/{$client->slug}/articles/create?calendar_entry={$entry->id}")->assertOk()->assertSee('Planned piece');
        $this->get("/bough/clients/{$client->slug}/articles/{$article->id}")->assertOk()->assertSee('article-show');
        $this->get("/bough/clients/{$client->slug}/articles/{$article->id}/edit")->assertOk()->assertSee('article-edit');

        $writer = $this->member($workspace, UserRole::Writer);
        $this->grantClientAccess($writer, $client);
        $this->actingAs($writer)->get("/bough/clients/{$client->slug}/articles/create")->assertForbidden();
        $this->get("/bough/clients/{$client->slug}/articles/{$article->id}/edit")->assertForbidden();
    }

    public function test_deleting_a_client_removes_its_content(): void
    {
        $workspace = $this->workspace();
        $client = $this->client($workspace);
        $pillar = $client->contentPillars()->create(['workspace_id' => $workspace->id, 'name' => 'Tax']);
        $article = $this->article($client, ['content_pillar_id' => $pillar->id]);
        $article->scores()->create(['workspace_id' => $workspace->id, 'score_type' => 'ai', 'overall_score' => 70]);
        $client->calendarEntries()->create(['workspace_id' => $workspace->id, 'title' => 'Plan', 'article_id' => $article->id, 'content_pillar_id' => $pillar->id]);

        $client->delete();

        foreach (['content_pillars', 'articles', 'content_calendar_entries', 'article_scores'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    private function url(Client $client, ?int $id = null): string
    {
        return "/{$client->workspace->slug}/api/clients/{$client->id}/articles".($id ? "/{$id}" : '');
    }

    private function scoresUrl(Article $article): string
    {
        return $this->url($article->client, $article->id).'/scores';
    }

    private function article(Client $client, array $attributes = []): Article
    {
        return $client->articles()->create(['workspace_id' => $client->workspace_id, 'title' => 'Draft article', ...$attributes]);
    }
}
