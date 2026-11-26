<?php

namespace App\Http\Requests\Upwork;

use App\Enums\UpworkSearch;
use App\Enums\UpworkTrack;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Structured Upwork settings. Sections not sent are left unchanged, and keys
 * this form does not know about are kept by UpworkSettingsService.
 */
class UpworkSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-platform-settings') ?? false;
    }

    public function rules(): array
    {
        $rules = [
            'automation_enabled' => ['sometimes', 'boolean'],
            'run_interval_minutes' => ['sometimes', 'integer', 'min:15', 'max:1440'],
            'tracks' => ['sometimes', 'array:'.implode(',', array_column(UpworkTrack::cases(), 'value'))],
            'searches' => ['sometimes', 'array:'.implode(',', array_column(UpworkSearch::cases(), 'value'))],

            'fit' => ['sometimes', 'array'],
            'fit.min_hourly_rate' => ['sometimes', 'numeric', 'min:0', 'max:1000'],
            'fit.min_fixed_budget' => ['sometimes', 'numeric', 'min:0', 'max:1000000'],
            'fit.max_proposals' => ['sometimes', 'integer', 'min:1', 'max:500'],
            'fit.max_job_age_minutes' => ['sometimes', 'integer', 'min:15', 'max:10080'],
            'fit.auto_accept_score' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'fit.review_score' => ['sometimes', 'integer', 'min:0', 'max:100', 'lte:fit.auto_accept_score'],

            'bidding' => ['sometimes', 'array'],
            'bidding.hourly_bid_standard' => ['sometimes', 'numeric', 'min:1', 'max:1000'],
            'bidding.hourly_bid_premium' => ['sometimes', 'numeric', 'min:1', 'max:1000', 'gte:bidding.hourly_bid_standard'],
            'bidding.daily_connects_cap' => ['sometimes', 'integer', 'min:0', 'max:10000'],
            'bidding.connects_balance' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100000'],
            'bidding.connects_balance_floor' => ['sometimes', 'integer', 'min:0', 'max:100000'],
            'bidding.send_delay_min_minutes' => ['sometimes', 'integer', 'min:0', 'max:1440'],
            'bidding.send_delay_max_minutes' => ['sometimes', 'integer', 'min:0', 'max:1440', 'gte:bidding.send_delay_min_minutes'],
            'bidding.proposal_expiry_minutes' => ['sometimes', 'integer', 'min:30', 'max:10080'],

            'prompts' => ['sometimes', 'array'],
            'prompts.fit_check' => ['sometimes', 'string', 'min:20', 'max:20000'],
            'prompts.cover_letter' => ['sometimes', 'string', 'min:20', 'max:20000'],
            'prompts.screening_answers' => ['sometimes', 'string', 'min:20', 'max:20000'],
        ];

        foreach (UpworkTrack::cases() as $track) {
            $rules["tracks.{$track->value}"] = ['sometimes', 'array'];
            $rules["tracks.{$track->value}.keywords"] = ['sometimes', 'array', 'max:25'];
            $rules["tracks.{$track->value}.keywords.*"] = ['string', 'min:2', 'max:100', 'distinct:ignore_case'];
        }

        foreach (UpworkSearch::cases() as $search) {
            $key = "searches.{$search->value}";
            $rules[$key] = ['sometimes', 'array'];
            $rules["{$key}.enabled"] = ['sometimes', 'boolean'];
            $rules["{$key}.job_type"] = ['sometimes', 'nullable', Rule::in(['hourly', 'fixed'])];
            $rules["{$key}.fixed_budget_min"] = ['sometimes', 'nullable', 'numeric', 'min:0'];
            $rules["{$key}.fixed_budget_max"] = ['sometimes', 'nullable', 'numeric', 'min:0'];
            $rules["{$key}.hourly_rate_min"] = ['sometimes', 'nullable', 'numeric', 'min:0'];
            $rules["{$key}.client_countries"] = ['sometimes', 'array', 'max:30'];
            $rules["{$key}.client_countries.*"] = ['string', 'min:2', 'max:80'];
            $rules["{$key}.experience_levels"] = ['sometimes', 'array'];
            $rules["{$key}.experience_levels.*"] = [Rule::in(['entry_level', 'intermediate', 'expert'])];
        }

        return $rules;
    }

    public function attributes(): array
    {
        return [
            'fit.review_score' => 'review cut-off',
            'fit.auto_accept_score' => 'auto-accept cut-off',
            'bidding.hourly_bid_premium' => 'premium hourly bid',
            'bidding.send_delay_max_minutes' => 'latest send delay',
        ];
    }
}
