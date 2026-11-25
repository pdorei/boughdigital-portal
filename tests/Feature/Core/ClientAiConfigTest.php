<?php

namespace Tests\Feature\Core;

use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\ClientAiConfig;
use App\Services\Clients\ClientAiConfigService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesWorkspaceFixtures;
use Tests\TestCase;

class ClientAiConfigTest extends TestCase
{
    use CreatesWorkspaceFixtures, RefreshDatabase;

    public function test_admin_can_create_and_update_the_configuration(): void
    {
        $workspace = $this->workspace();
        $client = $this->client($workspace);
        $this->actingAs($this->member($workspace));

        $this->getJson($this->url($client))
            ->assertOk()
            ->assertJsonPath('data.configured', false)
            ->assertJsonPath('data.brand_tone', null)
            ->assertJsonPath('data.key_urls', [])
            ->assertJsonPath('data.can.update', true);

        $urls = [
            ['label' => 'Website', 'url' => 'https://example.com'],
            ['label' => 'Services', 'url' => 'https://example.com/services'],
            ['label' => 'About', 'url' => 'https://example.com/about'],
        ];

        $this->putJson($this->url($client), [
            'brand_tone' => 'Professional, friendly and authoritative.',
            'writing_preferences' => "Use Australian English.\nPrefer short paragraphs.",
            'industry_context' => 'Digital marketing agency for Australian small businesses.',
            'key_urls' => $urls,
            'custom_instructions' => 'Never make claims about guaranteed rankings.',
        ])->assertCreated()
            ->assertJsonPath('data.configured', true)
            ->assertJsonPath('data.writing_preferences', "Use Australian English.\nPrefer short paragraphs.")
            ->assertJsonPath('data.key_urls', $urls);

        // A partial save only changes the fields that were sent.
        $this->putJson($this->url($client), ['brand_tone' => 'Confident but approachable.'])
            ->assertOk()
            ->assertJsonPath('data.brand_tone', 'Confident but approachable.')
            ->assertJsonPath('data.custom_instructions', 'Never make claims about guaranteed rankings.')
            ->assertJsonPath('data.key_urls', $urls);

        $this->putJson($this->url($client), ['key_urls' => [], 'industry_context' => ''])
            ->assertOk()
            ->assertJsonPath('data.key_urls', [])
            ->assertJsonPath('data.industry_context', null);

        $this->assertSame(1, ClientAiConfig::query()->where('client_id', $client->id)->count());
        $this->assertSame(3, ActivityLog::query()->where('action', 'client.ai_config_updated')->where('client_id', $client->id)->count());
    }

    public function test_each_client_has_a_single_configuration(): void
    {
        $workspace = $this->workspace();
        $client = $this->client($workspace);
        $admin = $this->member($workspace);
        $this->actingAs($admin);

        // The first save creates the row (201); later saves update it (200).
        $this->putJson($this->url($client), ['brand_tone' => 'First'])->assertCreated();
        $this->putJson($this->url($client), ['brand_tone' => 'Second'])->assertOk();

        $this->assertSame(1, ClientAiConfig::query()->count());
        $this->assertSame('Second', $client->refresh()->aiConfig->brand_tone);

        // The same service is what an MCP tool would call.
        app(ClientAiConfigService::class)->save($client, $admin, ['custom_instructions' => 'Via MCP'], 'mcp');
        $this->assertSame(1, ClientAiConfig::query()->count());
        $this->assertDatabaseHas('activity_log', ['action' => 'client.ai_config_updated', 'source' => 'mcp', 'client_id' => $client->id]);

        $this->expectException(UniqueConstraintViolationException::class);
        ClientAiConfig::query()->create(['client_id' => $client->id]);
    }

    public function test_key_urls_are_validated(): void
    {
        $workspace = $this->workspace();
        $client = $this->client($workspace);
        $this->actingAs($this->member($workspace));

        $this->putJson($this->url($client), ['key_urls' => [
            ['label' => 'Home', 'url' => 'not a url'],
            ['label' => '', 'url' => 'https://example.com/services'],
            ['label' => 'Files', 'url' => 'ftp://example.com/files'],
            ['label' => 'Script', 'url' => 'javascript:alert(1)'],
        ]])->assertUnprocessable()
            ->assertJsonValidationErrors(['key_urls.0.url', 'key_urls.1.label', 'key_urls.2.url', 'key_urls.3.url']);

        $this->putJson($this->url($client), ['key_urls' => ['home' => ['label' => 'Home', 'url' => 'https://example.com']]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('key_urls');

        $this->putJson($this->url($client), ['key_urls' => [['label' => 'Home', 'url' => 'https://example.com', 'secret' => 'x']]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('key_urls.0');

        $this->putJson($this->url($client), ['key_urls' => array_fill(0, ClientAiConfig::MAX_KEY_URLS + 1, ['label' => 'Page', 'url' => 'https://example.com'])])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('key_urls');

        $this->assertSame(0, ClientAiConfig::query()->count());
    }

    public function test_admin_can_reset_the_configuration(): void
    {
        $workspace = $this->workspace();
        $client = $this->client($workspace);
        $leader = $this->member($workspace, UserRole::TeamLeader);
        $this->grantClientAccess($leader, $client);

        $this->actingAs($leader)->putJson($this->url($client), ['brand_tone' => 'Warm'])->assertCreated()->assertJsonPath('data.can.reset', false);
        $this->deleteJson($this->url($client))->assertForbidden();

        $this->actingAs($this->member($workspace))->deleteJson($this->url($client))
            ->assertOk()
            ->assertJsonPath('data.configured', false)
            ->assertJsonPath('data.brand_tone', null);

        $this->assertSame(0, ClientAiConfig::query()->count());
        $this->assertDatabaseHas('activity_log', ['action' => 'client.ai_config_reset', 'client_id' => $client->id]);
    }

    public function test_configuration_is_isolated_between_workspaces_and_clients(): void
    {
        $workspace = $this->workspace();
        $client = $this->client($workspace);
        $siblingClient = $this->client($workspace, 'birch-dental');
        $client->aiConfig()->create(['brand_tone' => 'Private tone']);

        $other = $this->workspace('agency-two');
        $foreignUrl = "/{$other->slug}/api/clients/{$client->id}/ai-config";

        $this->actingAs($this->member($other));
        $this->getJson($foreignUrl)->assertNotFound();
        $this->putJson($foreignUrl, ['brand_tone' => 'Overwritten'])->assertNotFound();
        $this->deleteJson($foreignUrl)->assertNotFound();
        $this->getJson($this->url($client))->assertForbidden();
        $this->putJson($this->url($client), ['brand_tone' => 'Overwritten'])->assertForbidden();

        $this->actingAs($this->member($workspace))->getJson($this->url($siblingClient))
            ->assertOk()
            ->assertJsonPath('data.configured', false)
            ->assertJsonPath('data.brand_tone', null);

        $this->assertSame('Private tone', $client->refresh()->aiConfig->brand_tone);
    }

    public function test_configuration_permissions_follow_client_access_and_role(): void
    {
        $workspace = $this->workspace();
        $client = $this->client($workspace);
        $client->aiConfig()->create(['brand_tone' => 'Existing']);

        $this->actingAs($this->member($workspace, UserRole::Writer))->getJson($this->url($client))->assertForbidden();

        $writer = $this->member($workspace, UserRole::Writer);
        $this->grantClientAccess($writer, $client);
        $this->actingAs($writer)->getJson($this->url($client))
            ->assertOk()
            ->assertJsonPath('data.brand_tone', 'Existing')
            ->assertJsonPath('data.can.update', false)
            ->assertJsonPath('data.can.reset', false);
        $this->putJson($this->url($client), ['brand_tone' => 'Changed'])->assertForbidden();
        $this->deleteJson($this->url($client))->assertForbidden();

        $this->assertSame('Existing', $client->refresh()->aiConfig->brand_tone);
    }

    private function url(Client $client): string
    {
        return "/{$client->workspace->slug}/api/clients/{$client->id}/ai-config";
    }
}
