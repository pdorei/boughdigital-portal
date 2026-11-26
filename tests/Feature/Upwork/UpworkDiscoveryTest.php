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
    }

    private function keywords(array $content, array $wordpress = []): void
    {
        app(UpworkSettingsService::class)->update([
            'tracks' => ['content' => ['keywords' => $content], 'wordpress' => ['keywords' => $wordpress]],
        ], $this->superAdmin());
    }

    public function test_each_keyword_is_searched_once_per_run(): void
    {
        // The default settings have 8 keywords (4 per track) and all of A, B and C enabled.
        $client = $this->fakeUpwork([]);

        $run = app(UpworkJobDiscoveryService::class)->run('test')['run'];

        $this->assertSame(8, $client->searchCalls);
        $this->assertSame(8, $run->search_calls);
        $this->assertSame(0, $run->detail_calls);
        $this->assertSame(8, $run->api_calls);
        $this->assertCount(8, collect($client->queries)->pluck('keyword')->unique());
    }

    public function test_a_keyword_listed_under_both_tracks_is_searched_once(): void
    {
        $this->keywords(['seo', 'WordPress'], ['wordpress']);
        $client = $this->fakeUpwork([]);

        app(UpworkJobDiscoveryService::class)->run('test');

        $this->assertSame(2, $client->searchCalls);
    }

    public function test_searches_a_b_c_are_applied_in_code_and_recorded_on_the_run(): void
    {
        $this->keywords(['seo']);
        $this->fakeUpwork([
            // A only: fixed $2,000, US client.
            $this->node('fixed', ['hourlyBudgetMin' => null, 'hourlyBudgetMax' => null, 'amount' => ['rawValue' => '2000']]),
            // B and C: hourly $30-50, Australian client.
            $this->node('hourly-au', ['client' => ['location' => ['country' => 'Australia']]]),
            // C only: fixed $300 from Australia (below A's minimum).
            $this->node('small-au', ['hourlyBudgetMin' => null, 'hourlyBudgetMax' => null, 'amount' => ['rawValue' => '300'], 'client' => ['location' => ['country' => 'Australia']]]),
            // None: hourly top $18 from the US, and an entry-level job.
            $this->node('low', ['hourlyBudgetMin' => ['rawValue' => '10'], 'hourlyBudgetMax' => ['rawValue' => '18']]),
            $this->node('entry', ['experienceLevel' => 'ENTRY_LEVEL', 'client' => ['location' => ['country' => 'Australia']]]),
        ]);

        $run = app(UpworkJobDiscoveryService::class)->run('test')['run'];

        $this->assertSame(1, $run->search_calls);
        $this->assertSame(['a' => 1, 'b' => 1, 'c' => 2], $run->search_matches['counts']);
        $this->assertSame([
            '~01fixed' => ['a'],
            '~01hourly-au' => ['b', 'c'],
            '~01small-au' => ['c'],
        ], $run->search_matches['jobs']);
        $this->assertEqualsCanonicalizing(['~01fixed', '~01hourly-au', '~01small-au'], UpworkJob::query()->pluck('upwork_job_id')->all());
        $this->assertSame('b', UpworkJob::query()->where('upwork_job_id', '~01hourly-au')->first()->search_key->value);
    }

    public function test_disabled_searches_do_not_match(): void
    {
        $this->keywords(['seo']);
        app(UpworkSettingsService::class)->update(['searches' => ['a' => ['enabled' => false], 'b' => ['enabled' => false]]], $this->superAdmin());
        $this->fakeUpwork([
            $this->node('us'),
            $this->node('au', ['client' => ['location' => ['country' => 'Australia']]]),
        ]);

        $run = app(UpworkJobDiscoveryService::class)->run('test')['run'];

        $this->assertSame(['~01au'], UpworkJob::query()->pluck('upwork_job_id')->all());
        $this->assertSame(['a' => 0, 'b' => 0, 'c' => 1], $run->search_matches['counts']);
    }

    public function test_details_are_fetched_only_for_new_jobs_that_pass_the_hard_rules(): void
    {
        $this->keywords(['seo']);
        $client = $this->fakeUpwork([
            $this->node('good', ['details' => ['screeningQuestions' => ['Q1?', 'Q2?'], 'paymentVerified' => true]]),
            $this->node('crowded', ['totalApplicants' => 75]),
            $this->node('cheap', ['hourlyBudgetMin' => ['rawValue' => '25'], 'hourlyBudgetMax' => ['rawValue' => '26']]),
        ]);
        // "cheap" matches search B (top $26 >= $25) but fails the hard rule once the minimum is $27.
        app(UpworkSettingsService::class)->update(['fit' => ['min_hourly_rate' => 27]], $this->superAdmin());

        $run = app(UpworkJobDiscoveryService::class)->run('test')['run'];

        $this->assertSame(['good'], $client->detailRequests);
        $this->assertSame(1, $run->detail_calls);
        $this->assertSame(2, $run->api_calls);

        $good = UpworkJob::query()->where('upwork_job_id', '~01good')->first();
        $this->assertSame(['Q1?', 'Q2?'], $good->screening_questions);
        $this->assertTrue($good->client_payment_verified);
        $this->assertNotNull($good->details_fetched_at);
        $this->assertNull(UpworkJob::query()->where('upwork_job_id', '~01crowded')->first()->details_fetched_at);

        // A second run finds nothing new, so it makes no details calls.
        $second = app(UpworkJobDiscoveryService::class)->run('test')['run'];
        $this->assertSame(0, $second->detail_calls);
        $this->assertSame(0, $second->jobs_new);
        $this->assertSame(1, count($client->detailRequests));
    }

    public function test_jobs_older_than_the_maximum_age_are_dropped(): void
    {
        $this->keywords(['seo']);
        $this->fakeUpwork([
            $this->node('fresh', ['posted_minutes_ago' => 30]),
            $this->node('stale', ['posted_minutes_ago' => 91]),
        ]);

        $run = app(UpworkJobDiscoveryService::class)->run('test')['run'];

        $this->assertSame(1, $run->jobs_new);
        $this->assertDatabaseHas('upwork_jobs', ['upwork_job_id' => '~01fresh', 'status' => UpworkJobStatus::New->value]);
        $this->assertDatabaseMissing('upwork_jobs', ['upwork_job_id' => '~01stale']);
    }

    public function test_a_rate_limit_during_search_stops_the_run_without_retrying(): void
    {
        $this->keywords(['seo', 'content', 'blog']);
        $client = $this->fakeUpwork([$this->node('a1')], rateLimitAfter: 2);

        $run = app(UpworkJobDiscoveryService::class)->run('test')['run'];

        $this->assertSame(2, $client->calls);
        $this->assertSame(UpworkRunStatus::RateLimited, $run->status);
        $this->assertSame(2, $run->search_calls);
        $this->assertSame(0, $run->detail_calls);
        $this->assertSame('rate_limited', $run->errors[0]['type']);
        $this->assertNotNull($run->finished_at);
    }

    public function test_a_rate_limit_during_details_stops_the_run_without_retrying(): void
    {
        $this->keywords(['seo']);
        // Call 1 is the search, call 2 the first details call (rate limited).
        $client = $this->fakeUpwork([$this->node('one'), $this->node('two')], rateLimitAfter: 2);

        $result = app(UpworkJobDiscoveryService::class)->run('test');
        $run = $result['run'];

        $this->assertSame(2, $client->calls);
        $this->assertCount(1, $client->detailRequests);
        $this->assertSame(UpworkRunStatus::RateLimited, $run->status);
        $this->assertSame(1, $run->search_calls);
        $this->assertSame(1, $run->detail_calls);
        $this->assertSame(2, $run->api_calls);
        // Both jobs were stored and are still scored; neither has details.
        $this->assertCount(2, $result['new_job_ids']);
        $this->assertSame(0, UpworkJob::query()->whereNotNull('details_fetched_at')->count());
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
