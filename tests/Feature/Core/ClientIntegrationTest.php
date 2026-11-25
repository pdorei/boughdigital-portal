<?php

namespace Tests\Feature\Core;

use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\ClientIntegration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesWorkspaceFixtures;
use Tests\TestCase;

class ClientIntegrationTest extends TestCase
{
    use CreatesWorkspaceFixtures, RefreshDatabase;

    private const API_KEY = 'semrush-live-key-8f3a91c2d7e4';

    public function test_admin_can_connect_list_and_view_an_api_key_integration_without_exposing_the_key(): void
    {
        $workspace = $this->workspace();
        $client = $this->client($workspace);
        $this->actingAs($this->member($workspace));

        $created = $this->postJson($this->url($client), [
            'provider' => 'semrush',
            'name' => 'Main account',
            'credentials' => ['api_key' => self::API_KEY],
        ])->assertCreated()
            ->assertJsonPath('data.provider', 'semrush')
            ->assertJsonPath('data.provider_label', 'Semrush')
            ->assertJsonPath('data.auth_type', 'api_key')
            ->assertJsonPath('data.status', 'connected')
            ->assertJsonPath('data.credential_configured', true)
            ->assertJsonMissingPath('data.credentials');
        $this->assertStringNotContainsString(self::API_KEY, $created->getContent());

        $id = $created->json('data.id');

        $list = $this->getJson($this->url($client))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.display_name', 'Main account')
            ->assertJsonPath('meta.can_create', true);
        $this->assertStringNotContainsString(self::API_KEY, $list->getContent());

        $shown = $this->getJson($this->url($client, $id))->assertOk()->assertJsonPath('data.status', 'connected');
        $this->assertStringNotContainsString(self::API_KEY, $shown->getContent());

        // The client page bootstraps React props; they must not carry the key either.
        $this->assertStringNotContainsString(self::API_KEY, $this->get("/bough/clients/{$client->slug}")->assertOk()->getContent());

        $activity = ActivityLog::query()->where('action', 'integration.connected')->where('entity_id', $id)->sole();
        $this->assertSame($client->id, $activity->client_id);
        $this->assertSame('Main account', $activity->details['name']);
        $this->assertStringNotContainsString(self::API_KEY, json_encode($activity->details));
    }

    public function test_credentials_are_encrypted_at_rest(): void
    {
        $workspace = $this->workspace();
        $client = $this->client($workspace);
        $this->actingAs($this->member($workspace));

        $id = $this->postJson($this->url($client), ['provider' => 'semrush', 'credentials' => ['api_key' => self::API_KEY]])->json('data.id');

        $stored = DB::table('client_integrations')->where('id', $id)->value('credentials');
        $this->assertStringNotContainsString(self::API_KEY, $stored);
        $this->assertSame(['api_key' => self::API_KEY], json_decode(Crypt::decryptString($stored), true));

        $integration = ClientIntegration::query()->findOrFail($id);
        $this->assertSame(['api_key' => self::API_KEY], $integration->credentials);
        $this->assertArrayNotHasKey('credentials', $integration->toArray());
    }

    public function test_updating_keeps_the_saved_credential_unless_a_new_one_is_entered(): void
    {
        $workspace = $this->workspace();
        $client = $this->client($workspace);
        $this->actingAs($this->member($workspace));

        $id = $this->postJson($this->url($client), ['provider' => 'semrush', 'credentials' => ['api_key' => self::API_KEY]])->json('data.id');

        $this->patchJson($this->url($client, $id), ['name' => 'Agency account', 'credentials' => ['api_key' => '']])
            ->assertOk()
            ->assertJsonPath('data.name', 'Agency account')
            ->assertJsonPath('data.credential_configured', true);
        $this->assertSame(['api_key' => self::API_KEY], ClientIntegration::query()->findOrFail($id)->credentials);

        $this->patchJson($this->url($client, $id), ['credentials' => ['api_key' => 'replacement-key-5521']])->assertOk();
        $this->assertSame(['api_key' => 'replacement-key-5521'], ClientIntegration::query()->findOrFail($id)->credentials);

        $this->patchJson($this->url($client, $id), ['provider' => 'facebook'])->assertUnprocessable()->assertJsonValidationErrors('provider');

        $updates = ActivityLog::query()->where('action', 'integration.updated')->where('entity_id', $id)->orderBy('id')->pluck('details');
        $this->assertSame([['name'], ['credentials']], $updates->map(fn (array $details) => $details['fields'])->all());
        $this->assertStringNotContainsString('replacement-key-5521', $updates->toJson());
    }

    public function test_admin_can_disconnect_reconnect_and_delete_an_integration(): void
    {
        $workspace = $this->workspace();
        $client = $this->client($workspace);
        $this->actingAs($this->member($workspace));

        $id = $this->postJson($this->url($client), ['provider' => 'semrush', 'credentials' => ['api_key' => self::API_KEY]])->json('data.id');

        $this->postJson($this->url($client, $id).'/disconnect')
            ->assertOk()
            ->assertJsonPath('data.status', 'disconnected')
            ->assertJsonPath('data.credential_configured', false)
            ->assertJsonPath('data.connected_at', null);
        $this->assertNull(DB::table('client_integrations')->where('id', $id)->value('credentials'));

        // Renaming a disconnected integration does not reconnect it.
        $this->patchJson($this->url($client, $id), ['name' => 'Paused'])->assertOk()->assertJsonPath('data.status', 'disconnected');

        $this->patchJson($this->url($client, $id), ['credentials' => ['api_key' => 'new-key-7781']])
            ->assertOk()
            ->assertJsonPath('data.status', 'connected')
            ->assertJsonPath('data.credential_configured', true);

        $this->deleteJson($this->url($client, $id))->assertNoContent();
        $this->assertDatabaseMissing('client_integrations', ['id' => $id]);

        $actions = ActivityLog::query()->where('entity_id', $id)->where('action', 'like', 'integration.%')->orderBy('id')->pluck('action')->all();
        $this->assertSame(['integration.connected', 'integration.disconnected', 'integration.updated', 'integration.connected', 'integration.deleted'], $actions);
    }

    public function test_provider_rules_are_enforced(): void
    {
        $workspace = $this->workspace();
        $client = $this->client($workspace);
        $this->actingAs($this->member($workspace));

        $this->postJson($this->url($client), ['provider' => 'google_analytics', 'credentials' => ['access_token' => 'x']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('provider');

        $this->postJson($this->url($client), ['provider' => 'semrush'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('credentials.api_key');

        $this->postJson($this->url($client), ['provider' => 'semrush', 'credentials' => ['password' => 'x']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('credentials');

        $this->postJson($this->url($client), ['provider' => 'semrush', 'credentials' => ['api_key' => self::API_KEY]])->assertCreated();
        $this->postJson($this->url($client), ['provider' => 'semrush', 'credentials' => ['api_key' => 'second']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('provider');

        $this->postJson($this->url($client), ['provider' => 'other', 'credentials' => ['secret' => 'x']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('provider');

        $this->assertSame(1, $client->integrations()->count());
    }

    public function test_only_admins_manage_integrations_and_only_client_members_can_view_them(): void
    {
        $workspace = $this->workspace();
        $client = $this->client($workspace);
        $integration = $this->integration($client);
        $payload = ['provider' => 'semrush', 'credentials' => ['api_key' => 'x']];

        $this->actingAs($this->member($workspace, UserRole::Writer))->getJson($this->url($client))->assertForbidden();

        foreach ([UserRole::Writer, UserRole::TeamLeader] as $role) {
            $member = $this->member($workspace, $role);
            $this->grantClientAccess($member, $client);
            $this->actingAs($member);

            $this->getJson($this->url($client))
                ->assertOk()
                ->assertJsonPath('meta.can_create', false)
                ->assertJsonPath('data.0.can.update', false)
                ->assertJsonPath('data.0.can.delete', false);
            $this->postJson($this->url($client), $payload)->assertForbidden();
            $this->patchJson($this->url($client, $integration->id), ['name' => 'Changed'])->assertForbidden();
            $this->postJson($this->url($client, $integration->id).'/disconnect')->assertForbidden();
            $this->deleteJson($this->url($client, $integration->id))->assertForbidden();
        }

        $this->assertSame(['api_key' => self::API_KEY], $integration->refresh()->credentials);
        $this->assertNull($integration->name);
    }

    public function test_integrations_are_isolated_between_workspaces_and_clients(): void
    {
        $workspace = $this->workspace();
        $client = $this->client($workspace);
        $siblingClient = $this->client($workspace, 'birch-dental');
        $integration = $this->integration($client);

        $other = $this->workspace('agency-two');
        $foreign = "/{$other->slug}/api/clients/{$client->id}/integrations";
        $this->actingAs($this->member($other));

        $this->getJson($foreign)->assertNotFound();
        $this->getJson("{$foreign}/{$integration->id}")->assertNotFound();
        $this->postJson($foreign, ['provider' => 'semrush', 'credentials' => ['api_key' => 'planted']])->assertNotFound();
        $this->patchJson("{$foreign}/{$integration->id}", ['credentials' => ['api_key' => 'hijacked']])->assertNotFound();
        $this->postJson("{$foreign}/{$integration->id}/disconnect")->assertNotFound();
        $this->deleteJson("{$foreign}/{$integration->id}")->assertNotFound();

        $this->getJson($this->url($client))->assertForbidden();
        $this->getJson($this->url($client, $integration->id))->assertForbidden();

        $this->actingAs($this->member($workspace));
        $this->getJson($this->url($siblingClient, $integration->id))->assertNotFound();
        $this->deleteJson($this->url($siblingClient, $integration->id))->assertNotFound();

        $this->assertSame(['api_key' => self::API_KEY], $integration->refresh()->credentials);
        $this->assertSame(1, ClientIntegration::query()->count());
    }

    public function test_deleting_a_client_removes_its_integrations(): void
    {
        $workspace = $this->workspace();
        $client = $this->client($workspace);
        $integration = $this->integration($client);

        $this->actingAs($this->member($workspace))->deleteJson("/bough/api/clients/{$client->id}")->assertNoContent();

        $this->assertDatabaseMissing('client_integrations', ['id' => $integration->id]);
    }

    public function test_the_platform_integrations_list_still_works_without_exposing_credentials(): void
    {
        $workspace = $this->workspace();
        $client = $this->client($workspace);
        $this->integration($client);
        $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'workspace_id' => null]);

        $response = $this->actingAs($superAdmin)->getJson('/admin/api/integrations')
            ->assertOk()
            ->assertJsonPath('data.0.provider', 'semrush')
            ->assertJsonPath('data.0.status', 'connected')
            ->assertJsonPath('data.0.client.id', $client->id);

        $this->assertStringNotContainsString(self::API_KEY, $response->getContent());
    }

    private function url(Client $client, ?int $integrationId = null): string
    {
        return "/{$client->workspace->slug}/api/clients/{$client->id}/integrations".($integrationId ? "/{$integrationId}" : '');
    }

    private function integration(Client $client): ClientIntegration
    {
        return $client->integrations()->create([
            'provider' => 'semrush',
            'auth_type' => 'api_key',
            'credentials' => ['api_key' => self::API_KEY],
            'status' => 'connected',
            'connected_at' => now(),
        ]);
    }
}
