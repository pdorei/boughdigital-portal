<?php

namespace App\Services\Upwork;

use App\Enums\PlatformIntegrationProvider;
use App\Enums\UpworkJobStatus;
use App\Enums\UpworkProposalStatus;
use App\Enums\UpworkRunStatus;
use App\Models\UpworkJob;
use App\Models\UpworkProposal;
use App\Models\UpworkRun;
use App\Services\Anthropic\ClaudeClient;
use App\Services\Integrations\PlatformIntegrationService;

/**
 * Counts for the Upwork overview and the sidebar badges. "Today" uses the
 * business timezone (config upwork.timezone), not the viewer's.
 */
class UpworkOverviewService
{
    public function __construct(
        private PlatformIntegrationService $integrations,
        private UpworkSettingsService $settings,
        private UpworkProposalService $proposals,
        private ClaudeClient $claude,
    ) {}

    /**
     * @return array{review: int, proposals: int}
     */
    public function badges(): array
    {
        return [
            'review' => UpworkJob::query()->where('status', UpworkJobStatus::Review)->count(),
            'proposals' => UpworkProposal::query()->whereIn('status', [UpworkProposalStatus::Pending, UpworkProposalStatus::Approved])->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $settings = $this->settings->settings();
        $startOfDay = now(config('upwork.timezone'))->startOfDay()->utc();
        $runsToday = UpworkRun::query()->where('started_at', '>=', $startOfDay);
        $lastRun = UpworkRun::query()->latest('started_at')->first();
        $badges = $this->badges();

        return [
            'stats' => [
                'jobs_found_today' => UpworkJob::query()->where('first_seen_at', '>=', $startOfDay)->count(),
                'runs_today' => (clone $runsToday)->count(),
                'errors_today' => (clone $runsToday)->whereIn('status', [UpworkRunStatus::Failed, UpworkRunStatus::RateLimited])->count(),
                'api_calls_today' => (int) (clone $runsToday)->sum('api_calls'),
                'awaiting_review' => $badges['review'],
                'open_proposals' => $badges['proposals'],
                'sent_today' => UpworkProposal::query()->where('status', UpworkProposalStatus::Sent)->where('sent_at', '>=', $startOfDay)->count(),
                'connects_used_today' => $this->proposals->connectsUsedToday(),
            ],
            'automation' => [
                'enabled' => (bool) $settings['automation_enabled'],
                'interval_minutes' => (int) $settings['run_interval_minutes'],
                'upwork_connected' => $this->integrations->get(PlatformIntegrationProvider::Upwork)->isConnected(),
                'claude_connected' => $this->claude->isConfigured(),
                'claude_budget_remaining_usd' => $this->claude->isConfigured() ? $this->claude->budgetRemaining() : null,
                'driver' => config('upwork.driver'),
                'connects_blocked' => $this->proposals->connectsBlockedReason($settings['bidding']),
                'next_run_at' => $settings['automation_enabled'] && $lastRun
                    ? $lastRun->started_at->copy()->addMinutes((int) $settings['run_interval_minutes'])->toISOString()
                    : null,
            ],
            'last_run' => $lastRun ? $this->run($lastRun) : null,
            'recent_runs' => UpworkRun::query()->latest('started_at')->limit(15)->get()->map(fn (UpworkRun $run) => $this->run($run))->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function run(UpworkRun $run): array
    {
        return [
            'id' => $run->id,
            'status' => $run->status->value,
            'trigger' => $run->trigger,
            'started_at' => $run->started_at?->toISOString(),
            'finished_at' => $run->finished_at?->toISOString(),
            'api_calls' => $run->api_calls,
            'jobs_found' => $run->jobs_found,
            'jobs_new' => $run->jobs_new,
            'claude_tokens_used' => (int) $run->claude_tokens_used,
            'errors' => array_map(fn (array $error) => ['type' => $error['type'] ?? 'error', 'message' => $error['message'] ?? ''], $run->errors ?? []),
        ];
    }
}
