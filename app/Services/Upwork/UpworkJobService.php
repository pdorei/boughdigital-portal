<?php

namespace App\Services\Upwork;

use App\Enums\ActivityEvent;
use App\Enums\UpworkDecisionOutcome;
use App\Enums\UpworkDecisionStage;
use App\Enums\UpworkJobStatus;
use App\Enums\UpworkProposalStatus;
use App\Jobs\Upwork\GenerateUpworkProposal;
use App\Models\UpworkJob;
use App\Models\UpworkProposal;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use Illuminate\Validation\ValidationException;

/**
 * Human decisions on jobs (Review queue, "Send to review") and expiry.
 * Jobs are never deleted; every change is a status plus a decision record.
 */
class UpworkJobService
{
    public function __construct(
        private UpworkSettingsService $settings,
        private ActivityLogger $activity,
    ) {}

    /**
     * Yes accepts the job and queues a proposal draft; No rejects it.
     */
    public function review(UpworkJob $job, bool $approve, User $user, ?string $reason = null): UpworkJob
    {
        if ($job->status !== UpworkJobStatus::Review) {
            throw ValidationException::withMessages(['status' => 'This job is no longer awaiting review.']);
        }

        $outcome = $approve ? UpworkDecisionOutcome::Accept : UpworkDecisionOutcome::Reject;

        $job->decisions()->create([
            'stage' => UpworkDecisionStage::Human,
            'outcome' => $outcome,
            'reason' => $reason ?: ($approve ? 'Approved in the review queue.' : 'Declined in the review queue.'),
            'decided_by' => $user->id,
            'decided_at' => now(),
        ]);
        $job->forceFill(['status' => $outcome->jobStatus()])->save();

        $this->activity->log(ActivityEvent::UpworkJobReviewed, null, $user, $job, [
            'job_title' => $job->title,
            'upwork_job_id' => $job->upwork_job_id,
            'decision' => $approve ? 'yes' : 'no',
        ]);

        if ($approve) {
            GenerateUpworkProposal::dispatch($job->id, humanApproved: true);
        }

        return $job;
    }

    /**
     * Puts a skipped, rejected or expired job (or a new one) back in the review queue.
     */
    public function sendToReview(UpworkJob $job, User $user): UpworkJob
    {
        $allowed = [UpworkJobStatus::New, UpworkJobStatus::Skipped, UpworkJobStatus::Rejected, UpworkJobStatus::Expired, UpworkJobStatus::Accepted];

        if (! in_array($job->status, $allowed, true) || $job->proposal()->whereIn('status', [UpworkProposalStatus::Pending, UpworkProposalStatus::Approved, UpworkProposalStatus::Sent])->exists()) {
            throw ValidationException::withMessages(['status' => 'This job cannot be sent to review.']);
        }

        $job->decisions()->create([
            'stage' => UpworkDecisionStage::Human,
            'outcome' => UpworkDecisionOutcome::Review,
            'reason' => 'Sent to review manually.',
            'decided_by' => $user->id,
            'decided_at' => now(),
        ]);
        $job->forceFill(['status' => UpworkJobStatus::Review])->save();

        $this->activity->log(ActivityEvent::UpworkJobReviewed, null, $user, $job, [
            'job_title' => $job->title,
            'upwork_job_id' => $job->upwork_job_id,
            'decision' => 'sent_to_review',
        ]);

        return $job;
    }

    /**
     * Queues a new draft for an accepted job whose draft failed (e.g. Claude
     * was unavailable or the Connects cap was reached at the time).
     */
    public function redraft(UpworkJob $job): void
    {
        if ($job->status !== UpworkJobStatus::Accepted || $job->proposal()->exists()) {
            throw ValidationException::withMessages(['status' => 'Only an accepted job without a proposal can be drafted.']);
        }

        GenerateUpworkProposal::dispatch($job->id, humanApproved: true);
    }

    /**
     * Review jobs older than the maximum job age, accepted jobs that never got
     * a proposal, and open proposals past their expiry all become expired.
     *
     * @return array{jobs: int, proposals: int}
     */
    public function expireStale(): array
    {
        $settings = $this->settings->settings();
        $reviewCutoff = now()->subMinutes((int) $settings['fit']['max_job_age_minutes']);
        $proposalCutoff = now()->subMinutes((int) $settings['bidding']['proposal_expiry_minutes']);
        $jobs = 0;

        $stale = UpworkJob::query()
            ->where(function ($query) use ($reviewCutoff, $proposalCutoff): void {
                $query->where(fn ($q) => $q->where('status', UpworkJobStatus::Review)->whereRaw('coalesce(posted_at, first_seen_at) < ?', [$reviewCutoff]))
                    ->orWhere(fn ($q) => $q->whereIn('status', [UpworkJobStatus::Accepted, UpworkJobStatus::New])
                        ->whereDoesntHave('proposal')
                        ->whereRaw('coalesce(posted_at, first_seen_at) < ?', [$proposalCutoff]));
            })
            ->get();

        foreach ($stale as $job) {
            $this->expireJob($job, $job->status === UpworkJobStatus::Review ? 'Not reviewed in time.' : 'No proposal was sent in time.');
            $jobs++;
        }

        $proposals = UpworkProposal::query()
            ->with('job')
            ->whereIn('status', [UpworkProposalStatus::Pending, UpworkProposalStatus::Approved])
            ->where('expires_at', '<', now())
            ->get();

        foreach ($proposals as $proposal) {
            $proposal->forceFill(['status' => UpworkProposalStatus::Expired])->save();
            $this->expireJob($proposal->job, 'The proposal was not sent before it expired.');
        }

        return ['jobs' => $jobs, 'proposals' => $proposals->count()];
    }

    private function expireJob(UpworkJob $job, string $reason): void
    {
        $job->decisions()->create([
            'stage' => UpworkDecisionStage::System,
            'outcome' => UpworkDecisionOutcome::Skip,
            'reason' => $reason,
            'decided_at' => now(),
        ]);
        $job->forceFill(['status' => UpworkJobStatus::Expired])->save();
    }
}
