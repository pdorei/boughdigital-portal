<?php

namespace App\Models;

use App\Enums\UpworkDecisionOutcome;
use App\Enums\UpworkDecisionStage;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['upwork_job_id', 'stage', 'rule_result', 'fit_score', 'outcome', 'reason', 'red_flags', 'analysis', 'decided_by', 'decided_at'])]
class UpworkJobDecision extends Model
{
    public function job(): BelongsTo
    {
        return $this->belongsTo(UpworkJob::class, 'upwork_job_id');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    protected function casts(): array
    {
        return [
            'stage' => UpworkDecisionStage::class,
            'outcome' => UpworkDecisionOutcome::class,
            'red_flags' => 'array',
            'analysis' => 'array',
            'decided_at' => 'datetime',
        ];
    }
}
