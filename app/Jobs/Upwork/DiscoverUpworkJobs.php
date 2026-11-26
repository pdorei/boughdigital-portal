<?php

namespace App\Jobs\Upwork;

use App\Services\Upwork\UpworkJobDiscoveryService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * One discovery run, then a scoring job per new Upwork job. Not retried: a
 * failed or rate-limited run waits for the next schedule instead of looping.
 */
class DiscoverUpworkJobs implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public int $uniqueFor = 900;

    public function __construct(public string $trigger = 'schedule')
    {
        $this->onQueue(config('upwork.queue'));
    }

    public function handle(UpworkJobDiscoveryService $discovery): void
    {
        $result = $discovery->run($this->trigger);

        foreach ($result['new_job_ids'] as $jobId) {
            ScoreUpworkJob::dispatch($jobId);
        }
    }
}
