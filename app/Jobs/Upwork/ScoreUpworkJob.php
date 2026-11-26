<?php

namespace App\Jobs\Upwork;

use App\Enums\UpworkDecisionOutcome;
use App\Enums\UpworkJobStatus;
use App\Models\UpworkJob;
use App\Services\Upwork\UpworkFitService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Hard rules, then the Claude fit check. Claude failures are handled inside
 * the fit service (the job goes to review), so this is not retried.
 */
class ScoreUpworkJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 180;

    public function __construct(public int $upworkJobId)
    {
        $this->onQueue(config('upwork.queue'));
    }

    public function handle(UpworkFitService $fit): void
    {
        $job = UpworkJob::query()->find($this->upworkJobId);

        if (! $job || $job->status !== UpworkJobStatus::New) {
            return;
        }

        $result = $fit->evaluate($job);

        if ($result['tokens'] > 0 && $job->run) {
            $job->run->increment('claude_tokens_used', $result['tokens']);
        }

        if ($result['decision']->outcome === UpworkDecisionOutcome::Accept) {
            GenerateUpworkProposal::dispatch($job->id);
        }
    }
}
