<?php

namespace Tests\Feature\Upwork;

use App\Enums\UpworkDecisionStage;
use App\Enums\UpworkJobStatus;
use App\Enums\UpworkProposalStatus;
use App\Jobs\Upwork\GenerateUpworkProposal;
use App\Jobs\Upwork\ScoreUpworkJob;
use App\Models\UpworkJob;
use App\Models\UpworkProposal;
use App\Services\Upwork\UpworkFitService;
use App\Services\Upwork\UpworkJobDiscoveryService;
use App\Services\Upwork\UpworkJobService;
use App\Services\Upwork\UpworkProposalService;
use App\Services\Upwork\UpworkSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\UpworkFixtures;
use Tests\TestCase;

class UpworkFitAndProposalTest extends TestCase
{
    use RefreshDatabase;
    use UpworkFixtures;

    private function job(array $attributes = []): UpworkJob
    {
        return UpworkJob::query()->create([
            'upwork_job_id' => '~01'.uniqid(),
            'title' => 'SEO content writer',
            'description' => 'Long-form articles for a SaaS blog.',
            'url' => 'https://www.upwork.com/jobs/~01x',
            'track' => 'content',
            'job_type' => 'hourly',
            'hourly_min' => 30,
            'hourly_max' => 50,
            'proposals_count' => 5,
            'posted_at' => now()->subMinutes(10),
            'first_seen_at' => now(),
            'status' => UpworkJobStatus::New,
            ...$attributes,
        ]);
    }

    private function fitJson(int $score, string $outcome = 'accept'): string
    {
        return json_encode([
            'score' => $score, 'outcome' => $outcome, 'reason' => 'Clear scope and good client.', 'sector' => 'SaaS',
            'deliverables' => ['4 articles per month'], 'ongoing' => true, 'red_flags' => [],
        ]);
    }

    public function test_hard_rules_skip_jobs_without_calling_claude(): void
    {
        Http::fake();
        $this->connectClaude();

        $cases = [
            $this->job(['proposals_count' => 51]),
            $this->job(['hourly_min' => 11, 'hourly_max' => 19]),
            $this->job(['job_type' => 'fixed', 'hourly_min' => null, 'hourly_max' => null, 'fixed_budget' => 999]),
            $this->job(['posted_at' => now()->subMinutes(120)]),
        ];

        foreach ($cases as $job) {
            app(UpworkFitService::class)->evaluate($job);
            $job->refresh();

            $this->assertSame(UpworkJobStatus::Skipped, $job->status);
            $this->assertSame(UpworkDecisionStage::Rules, $job->latestDecision->stage);
            $this->assertSame('fail', $job->latestDecision->rule_result);
        }

        Http::assertNothingSent();
    }

    public function test_invalid_claude_json_sends_the_job_to_review(): void
    {
        $this->connectClaude();
        $answers = ['not json at all', '{"score": "high"}', json_encode(['score' => 140, 'outcome' => 'accept', 'reason' => 'x', 'sector' => '', 'deliverables' => [], 'ongoing' => true, 'red_flags' => []])];
        $this->fakeClaude(...$answers);

        foreach ($answers as $text) {
            $job = $this->job();

            app(UpworkFitService::class)->evaluate($job);
            $job->refresh();

            $this->assertSame(UpworkJobStatus::Review, $job->status, $text);
            $this->assertStringContainsString('invalid JSON', $job->latestDecision->reason);
        }
    }

    public function test_claude_not_connected_or_over_budget_sends_the_job_to_review(): void
    {
        Http::fake();
        $job = $this->job();

        app(UpworkFitService::class)->evaluate($job);
        $this->assertSame(UpworkJobStatus::Review, $job->refresh()->status);

        $this->connectClaude(cap: 0);
        $second = $this->job();
        app(UpworkFitService::class)->evaluate($second);

        $this->assertSame(UpworkJobStatus::Review, $second->refresh()->status);
        $this->assertStringContainsString('spending cap', $second->latestDecision->reason);
        Http::assertNothingSent();
    }

    public function test_score_cut_offs_decide_and_claude_can_only_be_more_cautious(): void
    {
        $this->connectClaude();
        $fit = app(UpworkFitService::class);
        $settings = ['auto_accept_score' => 75, 'review_score' => 40];

        $this->assertSame('accept', $fit->outcome(75, 'accept', $settings)->value);
        $this->assertSame('review', $fit->outcome(74, 'accept', $settings)->value);
        $this->assertSame('review', $fit->outcome(40, 'accept', $settings)->value);
        $this->assertSame('skip', $fit->outcome(39, 'accept', $settings)->value);
        $this->assertSame('review', $fit->outcome(90, 'review', $settings)->value);
        $this->assertSame('skip', $fit->outcome(50, 'accept', ['auto_accept_score' => 75, 'review_score' => 60])->value);
    }

    public function test_full_pipeline_from_fixture_to_sent_proposal(): void
    {
        $admin = $this->superAdmin();
        $this->connectUpwork();
        $this->connectClaude();
        $this->fakeUpwork([$this->node('pipe', ['screeningQuestions' => ['How do you research keywords?']])]);
        $this->fakeClaude(
            $this->fitJson(82),
            $this->coverLetter(180)." \u{2014} thanks",
            json_encode(['answers' => [['question' => 'How do you research keywords?', 'answer' => 'We start with intent.']]]),
        );

        $discovery = app(UpworkJobDiscoveryService::class)->run('test');
        foreach ($discovery['new_job_ids'] as $id) {
            ScoreUpworkJob::dispatchSync($id);
        }

        $job = UpworkJob::query()->firstOrFail();
        $proposal = $job->proposal;

        $this->assertSame(UpworkJobStatus::Accepted, $job->status);
        $this->assertNotNull($proposal);
        $this->assertSame(UpworkProposalStatus::Pending, $proposal->status);
        $this->assertSame('30.00', $proposal->bid_amount);
        $this->assertStringNotContainsString("\u{2014}", $proposal->cover_letter);
        $this->assertSame('We start with intent.', $proposal->screening_answers[0]['answer']);
        $this->assertTrue($proposal->target_send_at->gte(now()->subSecond()));
        $this->assertTrue($proposal->expires_at->equalTo($job->posted_at->copy()->addMinutes(180)));
        $this->assertGreaterThan(0, $job->run->refresh()->claude_tokens_used);

        $service = app(UpworkProposalService::class);

        try {
            $service->markSent($proposal, $admin, 6);
            $this->fail('A pending proposal must be approved before it is marked sent.');
        } catch (ValidationException) {
        }

        $service->approve($proposal, $admin);
        $service->markSent($proposal->refresh(), $admin, 6);

        $this->assertSame(UpworkProposalStatus::Sent, $proposal->refresh()->status);
        $this->assertSame(UpworkJobStatus::ProposalSent, $job->refresh()->status);
        $this->assertSame(6, $service->connectsUsedToday());
        $this->assertDatabaseHas('activity_log', ['action' => 'upwork.proposal_approved']);
        $this->assertDatabaseHas('activity_log', ['action' => 'upwork.proposal_sent']);
    }

    public function test_rate_rules_skip_or_review_before_drafting(): void
    {
        Http::fake();
        $this->connectClaude();

        $fixedNoBudget = $this->job(['job_type' => 'fixed', 'hourly_min' => null, 'hourly_max' => null, 'fixed_budget' => null, 'status' => UpworkJobStatus::Accepted]);
        app(UpworkProposalService::class)->generate($fixedNoBudget);

        $this->assertSame(UpworkJobStatus::Review, $fixedNoBudget->refresh()->status);
        $this->assertNull($fixedNoBudget->proposal);
        Http::assertNothingSent();
    }

    public function test_review_yes_queues_a_draft_and_no_rejects_without_deleting(): void
    {
        Queue::fake();
        $admin = $this->superAdmin();
        $service = app(UpworkJobService::class);

        $yes = $this->job(['status' => UpworkJobStatus::Review]);
        $no = $this->job(['status' => UpworkJobStatus::Review]);

        $service->review($yes, true, $admin);
        $service->review($no, false, $admin);

        $this->assertSame(UpworkJobStatus::Accepted, $yes->refresh()->status);
        $this->assertSame(UpworkJobStatus::Rejected, $no->refresh()->status);
        $this->assertSame(UpworkDecisionStage::Human, $no->latestDecision->stage);
        $this->assertSame($admin->id, $no->latestDecision->decided_by);
        Queue::assertPushed(GenerateUpworkProposal::class, fn ($job) => $job->upworkJobId === $yes->id && $job->humanApproved);
        $this->assertSame(2, UpworkJob::query()->count());
    }

    public function test_human_approved_fixed_job_without_budget_needs_a_bid_before_approval(): void
    {
        $admin = $this->superAdmin();
        $this->connectClaude();
        $this->fakeClaude($this->coverLetter(170));

        $job = $this->job(['job_type' => 'fixed', 'hourly_min' => null, 'hourly_max' => null, 'fixed_budget' => null, 'status' => UpworkJobStatus::Accepted]);
        $proposal = app(UpworkProposalService::class)->generate($job, humanApproved: true)['proposal'];

        $this->assertNull($proposal->bid_amount);
        $this->assertContains('No client budget: set the bid before approving.', $proposal->quality_flags);

        $this->expectException(ValidationException::class);
        app(UpworkProposalService::class)->approve($proposal, $admin);
    }

    public function test_connects_cap_stops_drafting(): void
    {
        Http::fake();
        $this->connectClaude();
        $admin = $this->superAdmin();
        app(UpworkSettingsService::class)->update(['bidding' => ['connects_balance' => 10, 'connects_balance_floor' => 20]], $admin);

        $job = $this->job(['status' => UpworkJobStatus::Accepted]);
        $result = app(UpworkProposalService::class)->generate($job);

        $this->assertNull($result['proposal']);
        $this->assertStringContainsString('below the floor', $job->refresh()->latestDecision->reason);
        Http::assertNothingSent();
    }

    public function test_expiry_marks_stale_review_jobs_and_open_proposals(): void
    {
        $reviewJob = $this->job(['status' => UpworkJobStatus::Review, 'posted_at' => now()->subMinutes(95)]);
        $proposalJob = $this->job(['status' => UpworkJobStatus::Accepted, 'posted_at' => now()->subMinutes(200)]);
        $proposal = UpworkProposal::query()->create([
            'upwork_job_id' => $proposalJob->id, 'bid_type' => 'hourly', 'bid_amount' => 25, 'cover_letter' => 'x',
            'status' => UpworkProposalStatus::Pending, 'expires_at' => now()->subMinutes(20),
        ]);

        $result = app(UpworkJobService::class)->expireStale();

        $this->assertSame(['jobs' => 1, 'proposals' => 1], $result);
        $this->assertSame(UpworkJobStatus::Expired, $reviewJob->refresh()->status);
        $this->assertSame(UpworkProposalStatus::Expired, $proposal->refresh()->status);
        $this->assertSame(UpworkJobStatus::Expired, $proposalJob->refresh()->status);
    }

    public function test_quality_flags_catch_contact_details_and_ranking_promises(): void
    {
        $flags = app(UpworkProposalService::class)->qualityFlags('Email me at rob@example.com and we guarantee first page rankings.');

        $this->assertTrue(collect($flags)->contains(fn ($flag) => str_contains($flag, 'contact details')));
        $this->assertTrue(collect($flags)->contains(fn ($flag) => str_contains($flag, 'rankings')));
        $this->assertTrue(collect($flags)->contains(fn ($flag) => str_contains($flag, 'words')));
    }
}
