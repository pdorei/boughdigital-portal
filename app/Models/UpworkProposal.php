<?php

namespace App\Models;

use App\Enums\UpworkJobType;
use App\Enums\UpworkProposalStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A drafted proposal. It is never submitted automatically: a person approves
 * it, pastes it into Upwork and then marks it as sent.
 */
#[Fillable(['upwork_job_id', 'bid_type', 'bid_amount', 'cover_letter', 'screening_answers', 'target_send_at', 'expires_at', 'status', 'approved_by', 'approved_at', 'sent_at', 'connects_spent', 'edited', 'rejection_reason'])]
class UpworkProposal extends Model
{
    public function job(): BelongsTo
    {
        return $this->belongsTo(UpworkJob::class, 'upwork_job_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    protected function casts(): array
    {
        return [
            'bid_type' => UpworkJobType::class,
            'status' => UpworkProposalStatus::class,
            'bid_amount' => 'decimal:2',
            'screening_answers' => 'array',
            'target_send_at' => 'datetime',
            'expires_at' => 'datetime',
            'approved_at' => 'datetime',
            'sent_at' => 'datetime',
            'edited' => 'boolean',
        ];
    }
}
