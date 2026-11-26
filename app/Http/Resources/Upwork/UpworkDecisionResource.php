<?php

namespace App\Http\Resources\Upwork;

use App\Models\UpworkJobDecision;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin UpworkJobDecision
 */
class UpworkDecisionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'stage' => $this->stage->value,
            'rule_result' => $this->rule_result,
            'fit_score' => $this->fit_score,
            'outcome' => $this->outcome->value,
            'reason' => $this->reason,
            'red_flags' => $this->red_flags ?? [],
            'sector' => $this->analysis['sector'] ?? null,
            'deliverables' => $this->analysis['deliverables'] ?? [],
            'ongoing' => $this->analysis['ongoing'] ?? null,
            'model' => $this->analysis['model'] ?? null,
            'decided_by' => $this->whenLoaded('decider', fn () => $this->decider?->only(['id', 'name'])),
            'decided_at' => $this->decided_at?->toISOString(),
        ];
    }
}
