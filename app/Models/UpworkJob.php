<?php

namespace App\Models;

use App\Enums\UpworkJobStatus;
use App\Enums\UpworkJobType;
use App\Enums\UpworkSearch;
use App\Enums\UpworkTrack;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A job found through Upwork's official API. Never deleted: rejecting or
 * skipping changes the status so the history can be used to tune the rules.
 */
#[Fillable([
    'workspace_id', 'upwork_run_id', 'upwork_job_id', 'title', 'description', 'url', 'track', 'search_key', 'keyword',
    'job_type', 'hourly_min', 'hourly_max', 'fixed_budget', 'experience_level', 'duration', 'skills', 'proposals_count',
    'client_country', 'client_payment_verified', 'client_total_spent', 'client_hire_rate', 'client_rating',
    'client_reviews_count', 'screening_questions', 'details_fetched_at', 'raw', 'posted_at', 'first_seen_at', 'status',
])]
class UpworkJob extends Model
{
    public function run(): BelongsTo
    {
        return $this->belongsTo(UpworkRun::class, 'upwork_run_id');
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(UpworkJobDecision::class)->orderByDesc('decided_at')->orderByDesc('id');
    }

    public function latestDecision(): HasOne
    {
        return $this->hasOne(UpworkJobDecision::class)->latestOfMany('decided_at');
    }

    public function proposal(): HasOne
    {
        return $this->hasOne(UpworkProposal::class);
    }

    /**
     * The highest hourly rate the client offers, used by the rate rules.
     */
    public function topHourlyRate(): ?float
    {
        $rate = $this->hourly_max ?? $this->hourly_min;

        return $rate === null ? null : (float) $rate;
    }

    protected function casts(): array
    {
        return [
            'track' => UpworkTrack::class,
            'search_key' => UpworkSearch::class,
            'job_type' => UpworkJobType::class,
            'status' => UpworkJobStatus::class,
            'hourly_min' => 'decimal:2',
            'hourly_max' => 'decimal:2',
            'fixed_budget' => 'decimal:2',
            'client_total_spent' => 'decimal:2',
            'client_hire_rate' => 'decimal:2',
            'client_rating' => 'decimal:2',
            'client_payment_verified' => 'boolean',
            'skills' => 'array',
            'screening_questions' => 'array',
            'raw' => 'array',
            'details_fetched_at' => 'datetime',
            'posted_at' => 'datetime',
            'first_seen_at' => 'datetime',
        ];
    }
}
