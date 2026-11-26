<?php

namespace App\Services\Upwork;

use App\Enums\UpworkJobType;
use App\Models\UpworkJob;

/**
 * Rules applied in code before Claude. Only a value that is known and fails a
 * rule skips a job; a missing value never does. An unknown or unverified
 * payment status caps the job at review: it is still scored, but never
 * accepted automatically and never skipped for that reason.
 */
class UpworkHardRules
{
    /**
     * @param  array<string, mixed>  $fit  the "fit" settings section
     */
    public function evaluate(UpworkJob $job, array $fit): HardRuleResult
    {
        $postedAt = $job->posted_at ?? $job->first_seen_at;

        if ($postedAt !== null && $postedAt->lt(now()->subMinutes((int) $fit['max_job_age_minutes']))) {
            return new HardRuleResult("Posted more than {$fit['max_job_age_minutes']} minutes ago.");
        }

        if ($job->proposals_count !== null && $job->proposals_count > (int) $fit['max_proposals']) {
            return new HardRuleResult("{$job->proposals_count} proposals already (limit {$fit['max_proposals']}).");
        }

        if ($job->job_type === UpworkJobType::Hourly) {
            $top = $job->topHourlyRate();

            if ($top !== null && $top > 0 && $top < (float) $fit['min_hourly_rate']) {
                return new HardRuleResult("Top hourly rate \${$top} is below the \${$fit['min_hourly_rate']} minimum.");
            }
        }

        if ($job->job_type === UpworkJobType::Fixed && $job->fixed_budget !== null && (float) $job->fixed_budget > 0
            && (float) $job->fixed_budget < (float) $fit['min_fixed_budget']) {
            return new HardRuleResult('Budget $'.number_format((float) $job->fixed_budget)." is below the \${$fit['min_fixed_budget']} minimum.");
        }

        $review = [];

        if ($job->client_payment_verified === null) {
            $review[] = 'Client payment status is unknown.';
        } elseif ($job->client_payment_verified === false) {
            $review[] = 'Client payment is not verified.';
        }

        return new HardRuleResult(null, $review);
    }
}
