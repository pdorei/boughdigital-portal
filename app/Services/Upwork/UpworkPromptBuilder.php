<?php

namespace App\Services\Upwork;

use App\Enums\UpworkJobType;
use App\Models\UpworkJob;
use Illuminate\Support\Str;

/**
 * Turns a job into the plain-text block the prompt templates insert with
 * {{job}}. Only job data goes to Claude; no credentials or internal IDs.
 */
class UpworkPromptBuilder
{
    /**
     * @param  array<string, string>  $variables
     */
    public function render(string $template, array $variables): string
    {
        return strtr($template, collect($variables)->mapWithKeys(fn ($value, $key) => ['{{'.$key.'}}' => $value])->all());
    }

    public function jobSummary(UpworkJob $job): string
    {
        $budget = $job->job_type === UpworkJobType::Hourly
            ? 'Hourly '.($job->hourly_min || $job->hourly_max ? '$'.($job->hourly_min ?? '?').' to $'.($job->hourly_max ?? '?').' per hour' : '(rate not stated)')
            : 'Fixed price '.($job->fixed_budget ? '$'.number_format((float) $job->fixed_budget) : '(budget not stated)');

        $lines = [
            'Title: '.$job->title,
            'Budget: '.$budget,
            'Experience level: '.($job->experience_level ?: 'not stated'),
            'Duration: '.($job->duration ?: 'not stated'),
            'Skills: '.(implode(', ', $job->skills ?? []) ?: 'not stated'),
            'Proposals so far: '.($job->proposals_count ?? 'unknown'),
            'Client: '.collect([
                $job->client_country,
                $job->client_payment_verified === null ? null : ($job->client_payment_verified ? 'payment verified' : 'payment not verified'),
                $job->client_total_spent !== null ? '$'.number_format((float) $job->client_total_spent).' spent' : null,
                $job->client_hire_rate !== null ? $job->client_hire_rate.'% hire rate' : null,
                $job->client_rating !== null ? $job->client_rating.' rating' : null,
                $job->client_reviews_count !== null ? $job->client_reviews_count.' reviews' : null,
            ])->filter()->implode(', '),
            'Description:',
            Str::limit((string) $job->description, 6000),
        ];

        return implode("\n", $lines);
    }
}
