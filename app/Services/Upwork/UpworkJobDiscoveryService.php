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
 * One discovery run: every keyword of every track is searched each enabled
 * way (A, B, C), newest first. Filters the API cannot apply are applied here,
 * jobs older than the maximum age are dropped, and results are de-duplicated
 * by upwork_job_id across searches, keywords and runs.
 *
 * Rate limits end the run immediately; the next scheduled run tries again.
 */
class UpworkJobDiscoveryService
{
    public function __construct(
        private UpworkClient $client,
        private UpworkSettingsService $settings,
        private PlatformIntegrationService $integrations,
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
        $run = UpworkRun::query()->create(['status' => UpworkRunStatus::Running, 'trigger' => $trigger, 'started_at' => now()]);
        $maxCalls = (int) config('upwork.max_api_calls_per_run', 60);
        $maxAge = (int) $settings['fit']['max_job_age_minutes'];
        $seen = [];
        $newIds = [];
        $status = UpworkRunStatus::Completed;

        try {
            foreach ($this->queries($settings) as $query) {
                if ($run->api_calls >= $maxCalls) {
                    $run->addError('api_call_cap', "Stopped after {$maxCalls} API calls (per-run cap).");
                    break;
                }

                $run->api_calls++;

                try {
                    $results = $this->client->searchJobs($query);
                } catch (UpworkRateLimitedException|UpworkAuthenticationException $exception) {
                    throw $exception;
                } catch (UpworkApiException $exception) {
                    $run->addError('api_error', $exception->getMessage(), ['keyword' => $query->keyword, 'search' => $query->search->value]);

                    continue;
                }

                foreach ($results as $job) {
                    if (isset($seen[$job->upworkJobId]) || ! $this->isFresh($job, $maxAge) || ! $this->matches($job, $query)) {
                        continue;
                    }

                    $seen[$job->upworkJobId] = true;
                    $stored = $this->store($job, $query, $run);

                    if ($stored) {
                        $newIds[] = $stored->id;
                    }
                }
            }
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
            'jobs_found' => count($seen),
            'jobs_new' => count($newIds),
        ])->save();

        return ['run' => $run, 'new_job_ids' => $newIds];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return iterable<UpworkSearchQuery>
     */
    public function queries(array $settings): iterable
    {
        foreach (UpworkTrack::cases() as $track) {
            $keywords = collect($settings['tracks'][$track->value]['keywords'] ?? [])
                ->map(fn ($keyword) => trim((string) $keyword))
                ->filter()
                ->unique(fn ($keyword) => Str::lower($keyword));

            foreach ($keywords as $keyword) {
                foreach (UpworkSearch::cases() as $search) {
                    $filters = $settings['searches'][$search->value] ?? [];

                    if (! ($filters['enabled'] ?? false)) {
                        continue;
                    }

                    yield new UpworkSearchQuery(
                        keyword: $keyword,
                        track: $track,
                        search: $search,
                        jobType: UpworkJobType::tryFrom((string) ($filters['job_type'] ?? '')),
                        filters: $filters,
                    );
                }
            }
        }
    }

    public function isFresh(UpworkJobData $job, int $maxAgeMinutes): bool
    {
        return $job->postedAt === null || $job->postedAt->gte(now()->subMinutes($maxAgeMinutes));
    }

    /**
     * The search's own filters, applied in code. A value the job does not
     * state (no rate, no level) does not exclude it; the fit rules decide.
     */
    public function matches(UpworkJobData $job, UpworkSearchQuery $query): bool
    {
        $filters = $query->filters;

        if ($query->jobType && $job->jobType !== $query->jobType) {
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
     * Stores a job seen for the first time; returns null when it already exists.
     */
    private function store(UpworkJobData $job, UpworkSearchQuery $query, UpworkRun $run): ?UpworkJob
    {
        $model = UpworkJob::query()->createOrFirst(
            ['upwork_job_id' => $job->upworkJobId],
            [
                ...$job->toAttributes(),
                'upwork_run_id' => $run->id,
                'track' => $query->track,
                'search_key' => $query->search,
                'keyword' => $query->keyword,
                'first_seen_at' => now(),
                'status' => UpworkJobStatus::New,
            ],
        );

        return $model->wasRecentlyCreated ? $model : null;
    }
}
