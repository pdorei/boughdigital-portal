<?php

namespace App\Jobs\Upwork;

use App\Models\UpworkJob;
use App\Services\Upwork\UpworkProposalService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Drafts a proposal for an accepted job. The draft waits for a person to
 * approve it; nothing is submitted to Upwork.
 */
class GenerateUpworkProposal implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(public int $upworkJobId, public bool $humanApproved = false)
    {
        $this->onQueue(config('upwork.queue'));
    }

    public function handle(UpworkProposalService $proposals): void
    {
        $job = UpworkJob::query()->find($this->upworkJobId);

        if (! $job) {
            return;
        }

        $result = $proposals->generate($job, $this->humanApproved);

        if ($result['tokens'] > 0 && $job->run) {
            $job->run->increment('claude_tokens_used', $result['tokens']);
        }
    }
}
