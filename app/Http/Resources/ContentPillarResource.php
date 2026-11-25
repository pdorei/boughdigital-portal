<?php

namespace App\Http\Resources;

use App\Models\ContentPillar;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ContentPillar */
class ContentPillarResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'name' => $this->name,
            'description' => $this->description,
            'color' => $this->color,
            'sort_order' => $this->sort_order,
            'active' => $this->active,
            'articles_count' => $this->whenCounted('articles'),
            'calendar_entries_count' => $this->whenCounted('calendarEntries'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'can' => $this->when($request->user() !== null, fn () => [
                'update' => $request->user()->can('update', $this->resource),
                'delete' => $request->user()->can('delete', $this->resource),
            ]),
        ];
    }
}
