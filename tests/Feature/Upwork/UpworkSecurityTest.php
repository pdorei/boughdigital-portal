<?php

namespace Tests\Feature\Upwork;

use App\Enums\UpworkJobStatus;
use App\Enums\UpworkProposalStatus;
use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\PlatformIntegration;
use App\Models\UpworkJob;
use App\Models\UpworkProposal;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Upwork\UpworkSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\UpworkFixtures;
use Tests\TestCase;

class UpworkSecurityTest extends TestCase
{
    use RefreshDatabase;
    use UpworkFixtures;

    private UpworkJob $job;

    private UpworkProposal $proposal;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $this->connectUpwork();
        $this->connectClaude();

        $this->job = UpworkJob::query()->create([
            'upwork_job_id' => '~01secure', 'title' => 'Job', 'url' => 'https://www.upwork.com/jobs/~01secure', 'track' => 'content',
            'job_type' => 'hourly', 'hourly_max' => 40, 'first_seen_at' => now(), 'posted_at' => now(), 'status' => UpworkJobStatus::Review,
            'raw' => ['note' => 'raw payload'],
        ]);
        $this->proposal = UpworkProposal::query()->create([
            'upwork_job_id' => $this->job->id, 'bid_type' => 'hourly', 'bid_amount' => 30, 'cover_letter' => 'Draft',
            'status' => UpworkProposalStatus::Pending, 'expires_at' => now()->addHour(),
        ]);
    }

    /**
     * @return list<array{0: string, 1: string, 2?: array<string, mixed>}>
     */
    private function routes(): array
    {
        $job = $this->job->id;
        $proposal = $this->proposal->id;

        return [
            ['get', '/admin/upwork/overview'],
            ['get', '/admin/upwork/review'],
            ['get', '/admin/upwork/proposals'],
            ['get', '/admin/upwork/jobs'],
            ['get', '/admin/settings'],
            ['get', '/admin/upwork/oauth/connect'],
            ['get', '/admin/upwork/oauth/callback?state=x&code=y'],
            ['getJson', '/admin/api/upwork/overview'],
            ['postJson', '/admin/api/upwork/runs'],
            ['getJson', '/admin/api/upwork/jobs'],
            ['getJson', "/admin/api/upwork/jobs/{$job}"],
            ['postJson', "/admin/api/upwork/jobs/{$job}/review", ['decision' => 'yes']],
            ['postJson', "/admin/api/upwork/jobs/{$job}/send-to-review"],
            ['postJson', "/admin/api/upwork/jobs/{$job}/draft-proposal"],
            ['getJson', '/admin/api/upwork/proposals'],
            ['getJson', "/admin/api/upwork/proposals/{$proposal}"],
            ['patchJson', "/admin/api/upwork/proposals/{$proposal}", ['cover_letter' => 'Changed']],
            ['postJson', "/admin/api/upwork/proposals/{$proposal}/approve"],
            ['postJson', "/admin/api/upwork/proposals/{$proposal}/reject"],
            ['postJson', "/admin/api/upwork/proposals/{$proposal}/sent"],
            ['getJson', '/admin/api/settings/upwork'],
            ['patchJson', '/admin/api/settings/upwork', ['automation_enabled' => true]],
            ['getJson', '/admin/api/connections'],
            ['patchJson', '/admin/api/connections/anthropic', ['api_key' => 'sk-ant-attacker-key-000000000000']],
            ['postJson', '/admin/api/connections/upwork/disconnect'],
            ['postJson', '/admin/api/connections/anthropic/disconnect'],
        ];
    }

    public function test_workspace_admin_gets_403_on_every_upwork_and_settings_route(): void
    {
        $workspace = Workspace::query()->create(['name' => 'Bough Digital', 'slug' => 'bough']);
        $admin = User::factory()->create(['workspace_id' => $workspace->id, 'role' => UserRole::Admin]);

        foreach ($this->routes() as $route) {
            [$method, $uri, $data] = [...$route, []];
            $response = str_starts_with($method, 'get') ? $this->actingAs($admin)->{$method}($uri) : $this->actingAs($admin)->{$method}($uri, $data);
            $response->assertForbidden();
        }

        $this->assertSame(UpworkJobStatus::Review, $this->job->refresh()->status);
        $this->assertSame(UpworkProposalStatus::Pending, $this->proposal->refresh()->status);
        $this->assertSame(self::CLAUDE_KEY, PlatformIntegration::query()->where('provider', 'anthropic')->first()->credentials['api_key']);
        $this->assertTrue(PlatformIntegration::query()->where('provider', 'upwork')->first()->isConnected());
    }

    public function test_other_roles_and_guests_are_blocked(): void
    {
        $workspace = Workspace::query()->create(['name' => 'Bough Digital', 'slug' => 'bough']);

        foreach ([UserRole::TeamLeader, UserRole::Employee, UserRole::Writer, UserRole::Va] as $role) {
            $user = User::factory()->create(['workspace_id' => $workspace->id, 'role' => $role]);

            $this->actingAs($user)->getJson('/admin/api/upwork/jobs')->assertForbidden();
            $this->actingAs($user)->getJson('/admin/api/connections')->assertForbidden();
        }

        auth()->logout();
        $this->get('/admin/upwork/overview')->assertRedirect('/login');
        $this->getJson('/admin/api/upwork/jobs')->assertUnauthorized();
        $this->getJson('/admin/api/connections')->assertUnauthorized();
    }

    public function test_credentials_never_appear_in_any_response_or_activity_log(): void
    {
        $admin = $this->superAdmin();
        $secrets = [self::CLAUDE_KEY, self::UPWORK_TOKEN, self::UPWORK_REFRESH, 'eyJpdiI'];

        $responses = [
            $this->actingAs($admin)->get('/admin/settings'),
            $this->actingAs($admin)->get('/admin/upwork/overview'),
            $this->actingAs($admin)->getJson('/admin/api/connections'),
            $this->actingAs($admin)->getJson('/admin/api/settings/upwork'),
            $this->actingAs($admin)->getJson('/admin/api/upwork/overview'),
            $this->actingAs($admin)->getJson('/admin/api/upwork/jobs'),
            $this->actingAs($admin)->getJson("/admin/api/upwork/jobs/{$this->job->id}"),
            $this->actingAs($admin)->getJson('/admin/api/upwork/proposals'),
            $this->actingAs($admin)->getJson("/admin/api/upwork/proposals/{$this->proposal->id}"),
            $this->actingAs($admin)->patchJson('/admin/api/connections/anthropic', ['model' => 'claude-opus-5-5']),
            $this->actingAs($admin)->patchJson('/admin/api/connections/anthropic', ['api_key' => 'sk-ant-new-SECRET-key-9876543210']),
            $this->actingAs($admin)->patchJson('/admin/api/settings/upwork', ['run_interval_minutes' => 30]),
        ];

        foreach ($responses as $response) {
            $response->assertSuccessful();
            foreach ([...$secrets, 'sk-ant-new-SECRET-key-9876543210', 'raw payload', '"credentials"'] as $secret) {
                $this->assertStringNotContainsString($secret, $response->getContent());
            }
        }

        $connections = $this->actingAs($admin)->getJson('/admin/api/connections')->json('data');
        $this->assertTrue($connections['anthropic']['credential_configured']);
        $this->assertTrue($connections['upwork']['connected']);
        $this->assertSame('claude-opus-5-5', $connections['anthropic']['model']);

        $this->actingAs($admin)->postJson('/admin/api/connections/upwork/disconnect')->assertOk();

        $logged = ActivityLog::query()->get()->toJson();
        foreach ([...$secrets, 'sk-ant-new-SECRET-key-9876543210'] as $secret) {
            $this->assertStringNotContainsString($secret, $logged);
        }
        $this->assertDatabaseHas('activity_log', ['action' => 'platform_integration.updated']);
        $this->assertDatabaseHas('activity_log', ['action' => 'upwork.disconnected']);
        $this->assertDatabaseHas('activity_log', ['action' => 'upwork.settings_updated']);
    }

    public function test_settings_update_keeps_unknown_keys_and_replaces_lists(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)->patchJson('/admin/api/settings/upwork', [
            'tracks' => ['content' => ['keywords' => ['one', 'two', 'three']]],
        ])->assertOk();

        // A key written by a future version of the form.
        $service = app(UpworkSettingsService::class);
        $reflection = new \ReflectionMethod($service, 'write');
        $reflection->invoke($service, [...(new \ReflectionMethod($service, 'stored'))->invoke($service), 'future_option' => ['x' => 1]]);

        $response = $this->actingAs($admin)->patchJson('/admin/api/settings/upwork', [
            'tracks' => ['content' => ['keywords' => ['only one']]],
            'fit' => ['max_proposals' => 30],
        ])->assertOk();

        $this->assertSame(['only one'], $response->json('data.tracks.content.keywords'));
        $this->assertSame(30, $response->json('data.fit.max_proposals'));
        $this->assertSame(75, $response->json('data.fit.auto_accept_score'));
        $this->assertSame(['x' => 1], $response->json('data.future_option'));
    }

    public function test_settings_validation_errors_are_returned_as_422(): void
    {
        $this->actingAs($this->superAdmin())->patchJson('/admin/api/settings/upwork', [
            'run_interval_minutes' => 1,
            'fit' => ['auto_accept_score' => 50, 'review_score' => 60],
            'searches' => ['d' => ['enabled' => true]],
        ])->assertUnprocessable()->assertJsonValidationErrors(['run_interval_minutes', 'fit.review_score', 'searches']);
    }

    public function test_super_admin_can_review_and_act_on_proposals_through_the_api(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)->postJson("/admin/api/upwork/jobs/{$this->job->id}/review", ['decision' => 'no'])
            ->assertOk()->assertJsonPath('data.status', 'rejected');

        $this->proposal->update(['screening_answers' => [['question' => 'Q1?', 'answer' => 'A1']]]);

        $this->actingAs($admin)->patchJson("/admin/api/upwork/proposals/{$this->proposal->id}", [
            'cover_letter' => 'Edited letter',
            'bid_amount' => 30,
            'screening_answers' => [['question' => 'Q1?', 'answer' => 'A1']],
        ])->assertOk()->assertJsonPath('data.edited', true);

        // Unchanged answers and bid are not reported as edited (jsonb reorders keys).
        $this->assertSame(['cover_letter'], ActivityLog::query()->where('action', 'upwork.proposal_edited')->latest('id')->first()->details['fields']);

        $this->actingAs($admin)->postJson("/admin/api/upwork/proposals/{$this->proposal->id}/sent")
            ->assertUnprocessable()->assertJsonValidationErrors('status');

        $this->actingAs($admin)->postJson("/admin/api/upwork/proposals/{$this->proposal->id}/approve")
            ->assertOk()->assertJsonPath('data.status', 'approved');

        $this->actingAs($admin)->postJson("/admin/api/upwork/proposals/{$this->proposal->id}/sent", ['connects_spent' => 8])
            ->assertOk()->assertJsonPath('data.status', 'sent');

        $this->assertDatabaseHas('upwork_proposals', ['id' => $this->proposal->id, 'connects_spent' => 8]);
        $this->assertDatabaseHas('activity_log', ['action' => 'upwork.proposal_edited']);
        $this->assertDatabaseHas('activity_log', ['action' => 'upwork.job_reviewed']);
    }
}
