<?php

namespace App\Http\Resources\Upwork;

use App\Enums\UpworkProposalStatus;
use App\Models\UpworkProposal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin UpworkProposal
 */
class UpworkProposalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $open = in_array($this->status, [UpworkProposalStatus::Pending, UpworkProposalStatus::Approved], true);

        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'bid_type' => $this->bid_type->value,
            'bid_amount' => $this->bid_amount !== null ? (float) $this->bid_amount : null,
            'cover_letter' => $this->cover_letter,
            'screening_answers' => $this->screening_answers ?? [],
            'quality_flags' => $this->quality_flags ?? [],
            'target_send_at' => $this->target_send_at?->toISOString(),
            'expires_at' => $this->expires_at?->toISOString(),
            'approved_by' => $this->whenLoaded('approver', fn () => $this->approver?->only(['id', 'name'])),
            'approved_at' => $this->approved_at?->toISOString(),
            'sent_at' => $this->sent_at?->toISOString(),
            'connects_spent' => $this->connects_spent,
            'edited' => $this->edited,
            'rejection_reason' => $this->rejection_reason,
            'created_at' => $this->created_at?->toISOString(),
            'job' => $this->whenLoaded('job', fn () => new UpworkJobResource($this->job)),
            'can' => [
                'edit' => $open,
                'approve' => $this->status === UpworkProposalStatus::Pending,
                'reject' => $open,
                'send' => $this->status === UpworkProposalStatus::Approved,
            ],
        ];
    }
}
