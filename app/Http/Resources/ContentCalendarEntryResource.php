<?php

namespace App\Http\Resources;

use App\Models\ContentCalendarEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ContentCalendarEntry */
class ContentCalendarEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'title' => $this->title,
            'target_keyword' => $this->target_keyword,
            'secondary_keywords' => $this->secondary_keywords ?? [],
            'status' => $this->status->value,
            'content_type' => $this->content_type->value,
            'search_intent' => $this->search_intent?->value,
            'due_date' => $this->due_date?->toDateString(),
            'publish_date' => $this->publish_date?->toDateString(),
            'notes' => $this->notes,
            'content_pillar_id' => $this->content_pillar_id,
            'assigned_writer_id' => $this->assigned_writer_id,
            'article_id' => $this->article_id,
            'pillar' => $this->whenLoaded('pillar', fn () => $this->pillar?->only(['id', 'name', 'color'])),
            'writer' => $this->whenLoaded('writer', fn () => $this->writer?->only(['id', 'name'])),
            'article' => $this->whenLoaded('article', fn () => $this->article ? [
                'id' => $this->article->id,
                'title' => $this->article->title,
                'status' => $this->article->status->value,
            ] : null),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'can' => $this->when($request->user() !== null, fn () => [
                'update' => $request->user()->can('update', $this->resource),
                'delete' => $request->user()->can('delete', $this->resource),
            ]),
        ];
    }
}
