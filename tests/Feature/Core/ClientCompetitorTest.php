<?php

namespace Tests\Feature\Core;

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\ClientCompetitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesWorkspaceFixtures;
use Tests\TestCase;

class ClientCompetitorTest extends TestCase
{
    use CreatesWorkspaceFixtures, RefreshDatabase;

    public function test_admin_can_create_view_update_and_delete_a_competitor(): void
    {
        $workspace = $this->workspace();
        $client = $this->client($workspace);
        $this->actingAs($this->member($workspace));

        $created = $this->postJson($this->url($client), [
            'competitor_name' => 'Rival Legal',
            'competitor_domain' => 'https://www.Rival-Legal.com.au/services/',
            'tier' => 'primary',
            'competitor_type' => 'direct',
            'industry' => 'Legal services',
            'contact' => ['name' => 'Sam Lee', 'email' => 'sam@rival-legal.com.au', 'phone' => ''],
            'social_media' => ['linkedin' => 'https://linkedin.com/company/rival', 'facebook' => null],
            'keywords' => ['family lawyer', ' Family Lawyer ', '', 'divorce lawyer'],
        ])->assertCreated()
            ->assertJsonPath('data.competitor_domain', 'rival-legal.com.au')
            ->assertJsonPath('data.website_url', 'https://rival-legal.com.au')
            ->assertJsonPath('data.tier', 'primary')
            ->assertJsonPath('data.competitor_type', 'direct')
            ->assertJsonPath('data.contact', ['name' => 'Sam Lee', 'email' => 'sam@rival-legal.com.au', 'phone' => null])
            ->assertJsonPath('data.social_media.linkedin', 'https://linkedin.com/company/rival')
            ->assertJsonPath('data.social_media.facebook', null)
            ->assertJsonPath('data.keywords', ['family lawyer', 'divorce lawyer'])
            ->assertJsonPath('data.can.update', true)
            ->assertJsonPath('data.can.delete', true);

        $id = $created->json('data.id');
        $this->assertSame(['name' => 'Sam Lee', 'email' => 'sam@rival-legal.com.au'], ClientCompetitor::query()->findOrFail($id)->contact);

        $this->getJson($this->url($client))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.limit', 10)
            ->assertJsonPath('meta.can_create', true);

        $this->getJson($this->url($client, $id))->assertOk()->assertJsonPath('data.competitor_name', 'Rival Legal');

        $this->patchJson($this->url($client, $id), ['tier' => 'secondary', 'notes' => "Strong on reviews.\nWeak on content."])
            ->assertOk()
            ->assertJsonPath('data.tier', 'secondary')
            ->assertJsonPath('data.notes', "Strong on reviews.\nWeak on content.")
            ->assertJsonPath('data.competitor_domain', 'rival-legal.com.au')
            ->assertJsonPath('data.keywords', ['family lawyer', 'divorce lawyer']);

        $this->deleteJson($this->url($client, $id))->assertNoContent();
        $this->assertDatabaseMissing('client_competitors', ['id' => $id]);

        foreach (['competitor.created', 'competitor.updated', 'competitor.deleted'] as $event) {
            $this->assertDatabaseHas('activity_log', ['action' => $event, 'entity_id' => $id, 'client_id' => $client->id, 'workspace_id' => $workspace->id]);
        }
    }

    public function test_required_fields_and_field_formats_are_validated(): void
    {
        $workspace = $this->workspace();
        $client = $this->client($workspace);
        $this->actingAs($this->member($workspace));

        $this->postJson($this->url($client), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['competitor_name', 'competitor_domain', 'tier']);

        $this->postJson($this->url($client), [
            'competitor_name' => 'Bad data',
            'competitor_domain' => 'example.com',
            'tier' => 'tertiary',
            'competitor_type' => 'friendly',
            'contact' => ['email' => 'not-an-email'],
            'social_media' => ['myspace' => 'https://myspace.com/x'],
            'keywords' => [str_repeat('k', 101)],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['tier', 'competitor_type', 'contact.email', 'social_media', 'keywords.0']);

        $this->postJson($this->url($client), [
            'competitor_name' => 'Bad profile',
            'competitor_domain' => 'example.com',
            'tier' => 'primary',
            'social_media' => ['facebook' => 'javascript:alert(1)'],
        ])->assertUnprocessable()->assertJsonValidationErrors(['social_media.facebook']);

        foreach (['not a domain', 'localhost', 'https://', 'javascript:alert(1)'] as $domain) {
            $this->postJson($this->url($client), ['competitor_name' => 'Bad domain', 'competitor_domain' => $domain, 'tier' => 'primary'])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('competitor_domain');
        }

        $this->assertSame(0, $client->competitors()->count());
    }

    public function test_a_client_can_have_at_most_ten_competitors(): void
    {
        $workspace = $this->workspace();
        $client = $this->client($workspace);
        $this->actingAs($this->member($workspace));

        foreach (range(1, 10) as $number) {
            $this->postJson($this->url($client), ['competitor_name' => "Rival {$number}", 'competitor_domain' => "rival{$number}.com", 'tier' => 'secondary'])
                ->assertCreated();
        }

        $this->postJson($this->url($client), ['competitor_name' => 'Rival 11', 'competitor_domain' => 'rival11.com', 'tier' => 'secondary'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('competitors');

        $this->assertSame(10, $client->competitors()->count());

        $existing = $client->competitors()->firstOrFail();
        $this->patchJson($this->url($client, $existing->id), ['competitor_name' => 'Renamed rival'])
            ->assertOk()
            ->assertJsonPath('data.competitor_name', 'Renamed rival');
    }

    public function test_duplicate_domains_are_rejected_per_client_but_allowed_across_clients(): void
    {
        $workspace = $this->workspace();
        $client = $this->client($workspace);
        $otherClient = $this->client($workspace, 'birch-dental');
        $this->actingAs($this->member($workspace));

        $this->postJson($this->url($client), ['competitor_name' => 'Example', 'competitor_domain' => 'example.com', 'tier' => 'primary'])->assertCreated();

        $this->postJson($this->url($client), ['competitor_name' => 'Example again', 'competitor_domain' => 'https://www.EXAMPLE.com/about', 'tier' => 'primary'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('competitor_domain');

        $this->postJson($this->url($otherClient), ['competitor_name' => 'Example', 'competitor_domain' => 'www.example.com', 'tier' => 'primary'])
            ->assertCreated()
            ->assertJsonPath('data.competitor_domain', 'example.com');

        $second = $this->postJson($this->url($client), ['competitor_name' => 'Second', 'competitor_domain' => 'second.com', 'tier' => 'primary'])->assertCreated();

        $this->patchJson($this->url($client, $second->json('data.id')), ['competitor_domain' => 'example.com'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('competitor_domain');
    }

    public function test_competitors_are_isolated_between_workspaces_and_clients(): void
    {
        $workspace = $this->workspace();
        $client = $this->client($workspace);
        $siblingClient = $this->client($workspace, 'birch-dental');
        $competitor = $this->competitor($client);

        $other = $this->workspace('agency-two');
        $otherAdmin = $this->member($other);
        $foreignUrl = "/{$other->slug}/api/clients/{$client->id}/competitors";

        $this->actingAs($otherAdmin);

        // Through their own workspace the client does not exist.
        $this->getJson($foreignUrl)->assertNotFound();
        $this->getJson("{$foreignUrl}/{$competitor->id}")->assertNotFound();
        $this->patchJson("{$foreignUrl}/{$competitor->id}", ['competitor_name' => 'Hijacked'])->assertNotFound();
        $this->deleteJson("{$foreignUrl}/{$competitor->id}")->assertNotFound();
        $this->postJson($foreignUrl, ['competitor_name' => 'Planted', 'competitor_domain' => 'planted.com', 'tier' => 'primary'])->assertNotFound();

        // Through the owning workspace they are not a member.
        $this->getJson($this->url($client))->assertForbidden();
        $this->deleteJson($this->url($client, $competitor->id))->assertForbidden();

        // Within one workspace a competitor is only reachable through its own client.
        $this->actingAs($this->member($workspace));
        $this->getJson($this->url($siblingClient, $competitor->id))->assertNotFound();
        $this->deleteJson($this->url($siblingClient, $competitor->id))->assertNotFound();

        $this->assertDatabaseHas('client_competitors', ['id' => $competitor->id, 'competitor_name' => 'Rival Legal', 'client_id' => $client->id]);
        $this->assertSame(0, $siblingClient->competitors()->count());
    }

    public function test_competitor_permissions_follow_client_access_and_role(): void
    {
        $workspace = $this->workspace();
        $client = $this->client($workspace);
        $competitor = $this->competitor($client);
        $payload = ['competitor_name' => 'New rival', 'competitor_domain' => 'new-rival.com', 'tier' => 'primary'];

        $outsider = $this->member($workspace, UserRole::Writer);
        $this->actingAs($outsider)->getJson($this->url($client))->assertForbidden();

        $writer = $this->member($workspace, UserRole::Writer);
        $this->grantClientAccess($writer, $client);
        $this->actingAs($writer)->getJson($this->url($client))
            ->assertOk()
            ->assertJsonPath('meta.can_create', false)
            ->assertJsonPath('data.0.can.update', false)
            ->assertJsonPath('data.0.can.delete', false);
        $this->postJson($this->url($client), $payload)->assertForbidden();
        $this->patchJson($this->url($client, $competitor->id), ['tier' => 'secondary'])->assertForbidden();
        $this->deleteJson($this->url($client, $competitor->id))->assertForbidden();

        $leader = $this->member($workspace, UserRole::TeamLeader);
        $this->grantClientAccess($leader, $client);
        $this->actingAs($leader)->postJson($this->url($client), $payload)->assertCreated();
        $this->patchJson($this->url($client, $competitor->id), ['tier' => 'secondary'])->assertOk();
        $this->deleteJson($this->url($client, $competitor->id))->assertForbidden();

        $this->assertDatabaseHas('client_competitors', ['id' => $competitor->id]);
    }

    public function test_logos_are_stored_privately_and_served_through_the_authorized_route(): void
    {
        Storage::fake('local');

        $workspace = $this->workspace();
        $client = $this->client($workspace);
        $competitor = $this->competitor($client);
        $this->actingAs($this->member($workspace));

        $response = $this->post($this->url($client, $competitor->id).'/logo', [
            'logo' => UploadedFile::fake()->image('rival.png', 120, 60),
        ], ['Accept' => 'application/json'])->assertOk();

        $first = $competitor->refresh()->logo_path;
        $this->assertSame('local', $competitor->logo_disk);
        $this->assertStringStartsWith("workspaces/{$workspace->id}/clients/{$client->id}/competitors/logo-{$competitor->id}-", $first);
        Storage::disk('local')->assertExists($first);

        $logoUrl = $response->json('data.logo_url');
        $this->assertStringStartsWith("/bough/api/clients/{$client->id}/competitors/{$competitor->id}/logo", $logoUrl);
        $response->assertJsonMissingPath('data.logo_path')->assertJsonMissingPath('data.logo_disk');
        $this->get($logoUrl)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->post($this->url($client, $competitor->id).'/logo', ['logo' => UploadedFile::fake()->create('logo.svg', 4, 'image/svg+xml')], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('logo');

        $this->post($this->url($client, $competitor->id).'/logo', ['logo' => UploadedFile::fake()->image('new.jpg')], ['Accept' => 'application/json'])->assertOk();
        $second = $competitor->refresh()->logo_path;
        $this->assertNotSame($first, $second);
        Storage::disk('local')->assertMissing($first);
        Storage::disk('local')->assertExists($second);

        $other = $this->workspace('agency-two');
        $this->actingAs($this->member($other))->get("/{$other->slug}/api/clients/{$client->id}/competitors/{$competitor->id}/logo")->assertNotFound();

        $this->actingAs($this->member($workspace))->deleteJson($this->url($client, $competitor->id).'/logo')
            ->assertOk()
            ->assertJsonPath('data.logo_url', null);
        Storage::disk('local')->assertMissing($second);

        $this->post($this->url($client, $competitor->id).'/logo', ['logo' => UploadedFile::fake()->image('again.png')], ['Accept' => 'application/json'])->assertOk();
        $third = $competitor->refresh()->logo_path;
        $this->deleteJson($this->url($client, $competitor->id))->assertNoContent();
        Storage::disk('local')->assertMissing($third);
    }

    public function test_deleting_a_client_removes_its_competitors_and_their_logos(): void
    {
        Storage::fake('local');

        $workspace = $this->workspace();
        $client = $this->client($workspace);
        $competitor = $this->competitor($client);
        $this->actingAs($this->member($workspace));

        $this->post($this->url($client, $competitor->id).'/logo', ['logo' => UploadedFile::fake()->image('rival.png')], ['Accept' => 'application/json'])->assertOk();
        $path = $competitor->refresh()->logo_path;

        $this->deleteJson("/bough/api/clients/{$client->id}")->assertNoContent();

        $this->assertDatabaseMissing('client_competitors', ['id' => $competitor->id]);
        Storage::disk('local')->assertMissing($path);
    }

    private function url(Client $client, ?int $competitorId = null): string
    {
        return "/{$client->workspace->slug}/api/clients/{$client->id}/competitors".($competitorId ? "/{$competitorId}" : '');
    }

    private function competitor(Client $client): ClientCompetitor
    {
        return $client->competitors()->create([
            'competitor_name' => 'Rival Legal',
            'competitor_domain' => 'rival-legal.com.au',
            'tier' => 'primary',
        ]);
    }
}
