<?php

namespace Tests\Feature\Upwork;

use App\Enums\UpworkJobStatus;
use App\Enums\UpworkRunStatus;
use App\Models\UpworkJob;
use App\Services\Upwork\UpworkJobDiscoveryService;
use App\Services\Upwork\UpworkSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\UpworkFixtures;
use Tests\TestCase;

class UpworkDiscoveryTest extends TestCase
{
    use RefreshDatabase;
    use UpworkFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        $this->connectUpwork();
        app(UpworkSettingsService::class)->update([
            'tracks' => ['content' => ['keywords' => ['content writer', 'seo']], 'wordpress' => ['keywords' => ['wordpress']]],
        ], $this->superAdmin());
    }

    public function test_jobs_are_deduplicated_across_searches_keywords_and_runs(): void
    {
        $client = $this->fakeUpwork([
            $this->node('a1', ['title' => 'SEO content writer wanted']),
            $this->node('a2', ['title' => 'WordPress developer', 'description' => 'wordpress build', 'hourlyBudgetMin' => null, 'hourlyBudgetMax' => null, 'amount' => ['rawValue' => '2000']]),
        ]);

        $first = app(UpworkJobDiscoveryService::class)->run('test');

        // 3 keywords x 3 searches; a1 matches both content keywords and several searches.
        $this->assertSame(9, $client->calls);
        $this->assertSame(9, $first['run']->api_calls);
        $this->assertSame(2, $first['run']->jobs_new);
        $this->assertSame(2, UpworkJob::query()->count());

        $second = app(UpworkJobDiscoveryService::class)->run('test');

        $this->assertSame(0, $second['run']->jobs_new);
        $this->assertSame(2, $second['run']->jobs_found);
        $this->assertSame(2, UpworkJob::query()->count());

        $job = UpworkJob::query()->where('upwork_job_id', '~01a1')->first();
        $this->assertSame('content', $job->track->value);
        $this->assertSame(UpworkJobStatus::New, $job->status);
        $this->assertSame($first['run']->id, $job->upwork_run_id);
        $this->assertSame('https://www.upwork.com/jobs/~01a1', $job->url);
    }

    public function test_jobs_older_than_the_maximum_age_are_dropped(): void
    {
        $this->fakeUpwork([
            $this->node('fresh', ['posted_minutes_ago' => 30]),
            $this->node('stale', ['posted_minutes_ago' => 91]),
        ]);

        $result = app(UpworkJobDiscoveryService::class)->run('test');

        $this->assertSame(1, $result['run']->jobs_new);
        $this->assertDatabaseHas('upwork_jobs', ['upwork_job_id' => '~01fresh']);
        $this->assertDatabaseMissing('upwork_jobs', ['upwork_job_id' => '~01stale']);
    }

    public function test_search_filters_the_api_cannot_apply_are_applied_in_code(): void
    {
        app(UpworkSettingsService::class)->update([
            'searches' => ['a' => ['enabled' => false], 'b' => ['enabled' => false], 'c' => ['enabled' => true]],
        ], $this->superAdmin());

        $client = $this->fakeUpwork([
            $this->node('au', ['client' => ['location' => ['country' => 'Australia']]]),
            $this->node('us', ['client' => ['location' => ['country' => 'United States']]]),
            $this->node('entry', ['experienceLevel' => 'ENTRY_LEVEL', 'client' => ['location' => ['country' => 'Australia']]]),
        ]);

        app(UpworkJobDiscoveryService::class)->run('test');

        $this->assertSame(3, $client->calls);
        $this->assertSame(['~01au'], UpworkJob::query()->pluck('upwork_job_id')->all());
    }

    public function test_search_a_keeps_fixed_jobs_from_1000_and_search_b_hourly_from_25(): void
    {
        app(UpworkSettingsService::class)->update([
            'tracks' => ['content' => ['keywords' => ['seo']], 'wordpress' => ['keywords' => []]],
            'searches' => ['c' => ['enabled' => false]],
        ], $this->superAdmin());

        $client = $this->fakeUpwork([
            $this->node('fixed-ok', ['hourlyBudgetMin' => null, 'hourlyBudgetMax' => null, 'amount' => ['rawValue' => '1000']]),
            $this->node('fixed-small', ['hourlyBudgetMin' => null, 'hourlyBudgetMax' => null, 'amount' => ['rawValue' => '600']]),
            $this->node('hourly-ok', ['hourlyBudgetMin' => ['rawValue' => '20'], 'hourlyBudgetMax' => ['rawValue' => '25']]),
            $this->node('hourly-low', ['hourlyBudgetMin' => ['rawValue' => '10'], 'hourlyBudgetMax' => ['rawValue' => '18']]),
        ]);

        app(UpworkJobDiscoveryService::class)->run('test');

        $this->assertSame(2, $client->calls);
        $this->assertEqualsCanonicalizing(['~01fixed-ok', '~01hourly-ok'], UpworkJob::query()->pluck('upwork_job_id')->all());
    }

    public function test_a_rate_limit_stops_the_run_without_retrying(): void
    {
        $client = $this->fakeUpwork([$this->node('a1')], rateLimitAfter: 2);

        $result = app(UpworkJobDiscoveryService::class)->run('test');

        $this->assertSame(2, $client->calls);
        $this->assertSame(UpworkRunStatus::RateLimited, $result['run']->status);
        $this->assertSame(2, $result['run']->api_calls);
        $this->assertSame('rate_limited', $result['run']->errors[0]['type']);
        $this->assertNotNull($result['run']->finished_at);
    }

    public function test_runs_are_due_only_when_automation_is_on_and_the_interval_has_passed(): void
    {
        $this->fakeUpwork([]);
        $discovery = app(UpworkJobDiscoveryService::class);

        $this->assertFalse($discovery->isDue(), 'automation is off by default');

        app(UpworkSettingsService::class)->update(['automation_enabled' => true, 'run_interval_minutes' => 60], $this->superAdmin());
        $this->assertTrue($discovery->isDue());

        $discovery->run('test');
        $this->assertFalse($discovery->isDue());

        $this->travel(61)->minutes();
        $this->assertTrue($discovery->isDue());
    }
}
