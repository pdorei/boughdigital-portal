<?php

namespace App\Services\Upwork;

use App\Enums\PlatformIntegrationProvider;
use App\Enums\UpworkJobStatus;
use App\Enums\UpworkJobType;
use App\Enums\UpworkRunStatus;
use App\Enums\UpworkSearch;
use App\Enums\UpworkTrack;
use App\Models\UpworkJob;
use App\Models\UpworkRun;
use App\Services\Integrations\PlatformIntegrationService;
use App\Services\Upwork\Client\UpworkApiException;
use App\Services\Upwork\Client\UpworkAuthenticationException;
use App\Services\Upwork\Client\UpworkClient;
use App\Services\Upwork\Client\UpworkJobData;
use App\Services\Upwork\Client\UpworkRateLimitedException;
use App\Services\Upwork\Client\UpworkSearchQuery;
use Illuminate\Support\Str;

/**
 * One discovery run, in two phases:
 *
 * 1. Search: every keyword is searched once (newest first). Searches A, B and
 *    C are filters applied in code to that result set, and the run records
 *    which of them each job matched. Results are de-duplicated by
 *    upwork_job_id across keywords and runs; old jobs are dropped.
 * 2. Details: each new job that passes the hard rules gets one job-details
 *    call (screening questions, payment verification).
 *
 * Both kinds of call are counted on the run. A rate limit in either phase
 * ends the run at once, with no retry; the next scheduled run tries again.
 */
class UpworkJobDiscoveryService
{
    public function __construct(
        private UpworkClient $client,
        private UpworkSettingsService $settings,
        private PlatformIntegrationService $integrations,
        private UpworkHardRules $rules,
    ) {}

    /**
     * Whether the scheduler should start a run now.
     */
    public function isDue(): bool
    {
        $settings = $this->settings->settings();

        if (! $settings['automation_enabled'] || ! $this->integrations->get(PlatformIntegrationProvider::Upwork)->isConnected()) {
            return false;
        }

        $last = UpworkRun::query()->latest('started_at')->first();

        if (! $last) {
            return true;
        }

        // A run that is still marked running blocks a new one for up to 30 minutes.
        if ($last->status === UpworkRunStatus::Running && $last->started_at->gt(now()->subMinutes(30))) {
            return false;
        }

        return $last->started_at->lte(now()->subMinutes(max(5, (int) $settings['run_interval_minutes']))->addSeconds(30));
    }

    /**
     * @return array{run: UpworkRun, new_job_ids: list<int>}
     */
    public function run(string $trigger = 'schedule'): array
    {
        $settings = $this->settings->settings();
        $run = UpworkRun::query()->create([
            'status' => UpworkRunStatus::Running,
            'trigger' => $trigger,
            'started_at' => now(),
            'api_calls' => 0,
            'search_calls' => 0,
            'detail_calls' => 0,
        ]);
        $maxCalls = (int) config('upwork.max_api_calls_per_run', 60);
        $maxAge = (int) $settings['fit']['max_job_age_minutes'];
        $searches = $this->enabledSearches($settings);

        /** @var array<string, list<string>> $matched upwork_job_id => matched search keys */
        $matched = [];
        /** @var list<UpworkJob> $newJobs */
        $newJobs = [];
        $status = UpworkRunStatus::Completed;

        try {
            foreach ($this->queries($settings) as $query) {
                if ($run->api_calls >= $maxCalls) {
                    $run->addError('api_call_cap', "Stopped after {$maxCalls} API calls (per-run cap).");
                    break;
                }

                $run->api_calls++;
                $run->search_calls++;

                try {
                    $results = $this->client->searchJobs($query);
                } catch (UpworkRateLimitedException|UpworkAuthenticationException $exception) {
                    throw $exception;
                } catch (UpworkApiException $exception) {
                    $run->addError('api_error', $exception->getMessage(), ['keyword' => $query->keyword]);

                    continue;
                }

                foreach ($results as $job) {
                    if (isset($matched[$job->upworkJobId]) || ! $this->isFresh($job, $maxAge)) {
                        continue;
                    }

                    $keys = $this->matchingSearches($job, $searches);

                    if ($keys === []) {
                        continue;
                    }

                    $matched[$job->upworkJobId] = $keys;

                    if ($stored = $this->store($job, $query, $keys, $run)) {
                        $newJobs[] = $stored;
                    }
                }
            }

            $this->fetchDetails($newJobs, $settings['fit'], $run, $maxCalls);
        } catch (UpworkRateLimitedException $exception) {
            $status = UpworkRunStatus::RateLimited;
            $run->addError('rate_limited', $exception->getMessage());
        } catch (UpworkAuthenticationException $exception) {
            $status = UpworkRunStatus::Failed;
            $run->addError('authentication', $exception->getMessage());
            $this->integrations->markError(PlatformIntegrationProvider::Upwork, $exception->getMessage());
        }

        $run->forceFill([
            'status' => $status,
            'finished_at' => now(),
            'jobs_found' => count($matched),
            'jobs_new' => count($newJobs),
            'search_matches' => $this->summariseMatches($matched),
        ])->save();

        return ['run' => $run, 'new_job_ids' => array_map(fn (UpworkJob $job) => $job->id, $newJobs)];
    }

    /**
     * One query per keyword; a keyword listed under both tracks is searched once
     * (the first track wins).
     *
     * @param  array<string, mixed>  $settings
     * @return list<UpworkSearchQuery>
     */
    public function queries(array $settings): array
    {
        $queries = [];
        $seen = [];

        foreach (UpworkTrack::cases() as $track) {
            foreach ($settings['tracks'][$track->value]['keywords'] ?? [] as $keyword) {
                $keyword = trim((string) $keyword);
                $key = Str::lower($keyword);

                if ($keyword === '' || isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;
                $queries[] = new UpworkSearchQuery($keyword, $track);
            }
        }

        return $queries;
    }

    public function isFresh(UpworkJobData $job, int $maxAgeMinutes): bool
    {
        return $job->postedAt === null || $job->postedAt->gte(now()->subMinutes($maxAgeMinutes));
    }

    /**
     * Which enabled searches (A, B, C) a job satisfies.
     *
     * @param  array<string, array<string, mixed>>  $searches
     * @return list<string>
     */
    public function matchingSearches(UpworkJobData $job, array $searches): array
    {
        return array_values(array_keys(array_filter($searches, fn (array $filters) => $this->matches($job, $filters))));
    }

    /**
     * One search's filters. A value the job does not state (no rate, no level)
     * does not exclude it; the hard rules and fit check decide.
     *
     * @param  array<string, mixed>  $filters
     */
    public function matches(UpworkJobData $job, array $filters): bool
    {
        $jobType = UpworkJobType::tryFrom((string) ($filters['job_type'] ?? ''));

        if ($jobType && $job->jobType !== $jobType) {
            return false;
        }

        $levels = array_map('strtolower', $filters['experience_levels'] ?? []);
        if ($levels !== [] && $job->experienceLevel && ! in_array($job->experienceLevel, ['none', ...$levels], true)) {
            return false;
        }

        if (isset($filters['fixed_budget_min']) && $job->jobType === UpworkJobType::Fixed) {
            if ($job->fixedBudget === null || $job->fixedBudget < (float) $filters['fixed_budget_min']) {
                return false;
            }
        }

        if (filled($filters['fixed_budget_max'] ?? null) && $job->fixedBudget !== null && $job->fixedBudget > (float) $filters['fixed_budget_max']) {
            return false;
        }

        $topRate = $job->hourlyMax ?? $job->hourlyMin;
        if (isset($filters['hourly_rate_min']) && $job->jobType === UpworkJobType::Hourly && $topRate !== null && $topRate < (float) $filters['hourly_rate_min']) {
            return false;
        }

        $countries = array_map(fn ($country) => Str::lower(trim((string) $country)), $filters['client_countries'] ?? []);
        if ($countries !== [] && ! in_array(Str::lower((string) $job->clientCountry), $countries, true)) {
            return false;
        }

        return true;
    }

    /**
     * @param  list<UpworkJob>  $jobs
     * @param  array<string, mixed>  $fit
     */
    private function fetchDetails(array $jobs, array $fit, UpworkRun $run, int $maxCalls): void
    {
        foreach ($jobs as $job) {
            if ($this->rules->evaluate($job, $fit)->skips()) {
                continue;
            }

            if ($run->api_calls >= $maxCalls) {
                $run->addError('api_call_cap', "Stopped job details after {$maxCalls} API calls (per-run cap).");

                return;
            }

            $run->api_calls++;
            $run->detail_calls++;

            try {
                $details = $this->client->jobDetails((string) ($job->raw['id'] ?? $job->upwork_job_id));
            } catch (UpworkRateLimitedException|UpworkAuthenticationException $exception) {
                throw $exception;
            } catch (UpworkApiException $exception) {
                $run->addError('details_error', $exception->getMessage(), ['upwork_job_id' => $job->upwork_job_id]);

                continue;
            }

            if ($details === null) {
                $run->addError('details_missing', 'Job details were not returned.', ['upwork_job_id' => $job->upwork_job_id]);

                continue;
            }

            $job->forceFill([...$details->toAttributes(), 'details_fetched_at' => now()])->save();
        }
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, array<string, mixed>>
     */
    private function enabledSearches(array $settings): array
    {
        $searches = [];

        foreach (UpworkSearch::cases() as $search) {
            $filters = $settings['searches'][$search->value] ?? [];

            if ($filters['enabled'] ?? false) {
                $searches[$search->value] = $filters;
            }
        }

        return $searches;
    }

    /**
     * @param  array<string, list<string>>  $matched
     * @return array{counts: array<string, int>, jobs: array<string, list<string>>}
     */
    private function summariseMatches(array $matched): array
    {
        $counts = array_fill_keys(array_column(UpworkSearch::cases(), 'value'), 0);

        foreach ($matched as $keys) {
            foreach ($keys as $key) {
                $counts[$key]++;
            }
        }

        return ['counts' => $counts, 'jobs' => $matched];
    }

    /**
     * Stores a job seen for the first time; returns null when it already exists.
     *
     * @param  list<string>  $searchKeys
     */
    private function store(UpworkJobData $job, UpworkSearchQuery $query, array $searchKeys, UpworkRun $run): ?UpworkJob
    {
        $model = UpworkJob::query()->createOrFirst(
            ['upwork_job_id' => $job->upworkJobId],
            [
                ...$job->toAttributes(),
                'upwork_run_id' => $run->id,
                'track' => $query->track,
                'search_key' => $searchKeys[0],
                'keyword' => $query->keyword,
                'first_seen_at' => now(),
                'status' => UpworkJobStatus::New,
            ],
        );

        return $model->wasRecentlyCreated ? $model : null;
    }
}
