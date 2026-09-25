<?php

namespace Tests\Feature\Core;

use App\Enums\UserRole;
use App\Models\ActivityLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\CreatesWorkspaceFixtures;
use Tests\TestCase;

class ActivityAndAccessTest extends TestCase
{
    use CreatesWorkspaceFixtures, RefreshDatabase;

    public function test_workspace_activity_is_paginated_readable_and_limited_to_admins(): void
    {
        $workspace = $this->workspace();
        $admin = $this->member($workspace);
        $client = $this->client($workspace);
        $writer = $this->member($workspace, UserRole::Writer);
        $this->grantClientAccess($writer, $client);

        $this->actingAs($admin);
        $this->patchJson("/bough/api/clients/{$client->id}", ['name' => 'Acorn Legal Group'])->assertOk();
        $this->postJson('/bough/api/tasks', ['title' => 'Unrelated'])->assertCreated();

        $this->getJson('/bough/api/activity?per_page=1')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.label', 'Task created')
            ->assertJsonPath('data.0.actor', $admin->name)
            ->assertJsonMissingPath('data.0.details');

        $this->actingAs($writer);
        $this->getJson('/bough/api/activity')->assertForbidden();
        $this->get('/bough/activity')->assertForbidden();

        $this->getJson("/bough/api/activity?client_id={$client->id}")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.label', 'Client updated')
            ->assertJsonPath('data.0.subject', 'Acorn Legal Group');
    }

    public function test_activity_from_another_workspace_is_never_returned(): void
    {
        $workspace = $this->workspace();
        $other = $this->workspace('agency-two');
        $foreignClient = $this->client($other, 'foreign-client');
        ActivityLog::query()->create(['workspace_id' => $other->id, 'client_id' => $foreignClient->id, 'action' => 'client.updated', 'source' => 'portal']);

        $this->actingAs($this->member($workspace));

        $this->getJson('/bough/api/activity')->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson("/bough/api/activity?client_id={$foreignClient->id}")->assertNotFound();
    }

    public function test_workspace_admins_can_update_settings_without_losing_unknown_keys(): void
    {
        $workspace = $this->workspace();
        $workspace->forceFill(['settings' => ['timezone' => 'Australia/Perth', 'report_day' => 'monday']])->save();

        $this->actingAs($this->member($workspace))
            ->post('/bough/settings/date-time', ['timezone' => 'Asia/Phnom_Penh', 'date_format' => 'Y-m-d', 'datetime_format' => ''])
            ->assertRedirect();

        $this->assertSame(
            ['timezone' => 'Asia/Phnom_Penh', 'report_day' => 'monday', 'date_format' => 'Y-m-d'],
            $workspace->refresh()->settings,
        );
        $this->assertDatabaseHas('activity_log', ['action' => 'workspace.settings_updated', 'workspace_id' => $workspace->id]);

        $this->actingAs($this->member($workspace, UserRole::Employee))
            ->post('/bough/settings/date-time', ['timezone' => 'UTC'])
            ->assertForbidden();
    }

    public function test_deactivated_users_lose_their_session_on_the_next_request(): void
    {
        $workspace = $this->workspace();
        $user = $this->member($workspace, UserRole::Employee);

        $this->actingAs($user)->get('/bough/dashboard')->assertOk();

        $user->forceFill(['is_active' => false])->save();

        $this->get('/bough/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_users_without_an_active_workspace_get_a_clear_message_instead_of_a_broken_redirect(): void
    {
        $workspace = $this->workspace();
        $workspace->forceFill(['is_active' => false])->save();
        $this->member($workspace, UserRole::Employee, ['email' => 'orphan@example.com', 'password' => Hash::make('password')]);

        $this->from('/login')->post('/login', ['email' => 'orphan@example.com', 'password' => 'password'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->get('/')->assertRedirect('/login');
    }

    public function test_successful_login_is_recorded(): void
    {
        $workspace = $this->workspace();
        $user = $this->member($workspace, UserRole::Employee, ['email' => 'login@example.com', 'password' => Hash::make('password')]);

        $this->post('/login', ['email' => 'login@example.com', 'password' => 'password'])->assertRedirect('/bough/dashboard');

        $this->assertDatabaseHas('activity_log', ['action' => 'user.login', 'user_id' => $user->id, 'workspace_id' => $workspace->id]);
    }

    public function test_page_payload_is_scoped_to_what_the_user_may_see(): void
    {
        $workspace = $this->workspace();
        $other = $this->workspace('agency-two');
        $writer = $this->member($workspace, UserRole::Writer);

        $html = $this->actingAs($writer)->get('/bough/dashboard')->assertOk()->getContent();

        $this->assertStringNotContainsString('agency-two', $html);
        $this->assertStringContainsString('"id":'.$writer->id, $html);
        $this->assertStringContainsString('"team.manage":false', $html);
    }
}
