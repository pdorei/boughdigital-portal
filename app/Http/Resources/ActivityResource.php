<?php

namespace App\Http\Resources;

use App\Enums\ActivityEvent;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Deliberately exposes a summary rather than the raw details payload, which
 * can contain before/after snapshots meant for audit review only.
 *
 * @mixin ActivityLog
 */
class ActivityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'label' => ActivityEvent::labelFor($this->action),
            'subject' => $this->details['name'] ?? $this->details['title'] ?? null,
            'actor' => $this->user?->name,
            'client' => $this->whenLoaded('client', fn () => $this->client?->only(['id', 'name', 'slug'])),
            'source' => $this->source,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
