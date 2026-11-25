<?php

namespace App\Http\Resources;

use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Article */
class ArticleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'title' => $this->title,
            'slug' => $this->slug,
            'status' => $this->status->value,
            'primary_keyword' => $this->primary_keyword,
            'secondary_keywords' => $this->secondary_keywords ?? [],
            'search_intent' => $this->search_intent?->value,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'canonical_url' => $this->canonical_url,
            'content_body' => $this->whenHas('content_body'),
            'excerpt' => $this->excerpt,
            'word_count' => $this->word_count,
            'published_url' => $this->published_url,
            'published_at' => $this->published_at?->toISOString(),
            'tags' => $this->tags ?? [],
            'content_pillar_id' => $this->content_pillar_id,
            'assigned_writer_id' => $this->assigned_writer_id,
            'pillar' => $this->whenLoaded('pillar', fn () => $this->pillar?->only(['id', 'name', 'color'])),
            'writer' => $this->whenLoaded('writer', fn () => $this->writer?->only(['id', 'name'])),
            'calendar_entry' => $this->whenLoaded('calendarEntry', fn () => $this->calendarEntry ? [
                'id' => $this->calendarEntry->id,
                'title' => $this->calendarEntry->title,
                'publish_date' => $this->calendarEntry->publish_date?->toDateString(),
            ] : null),
            'latest_ai_score' => $this->whenLoaded('latestAiScore', fn () => $this->latestAiScore ? new ArticleScoreResource($this->latestAiScore) : null),
            'latest_human_score' => $this->whenLoaded('latestHumanScore', fn () => $this->latestHumanScore ? new ArticleScoreResource($this->latestHumanScore) : null),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'can' => $this->when($request->user() !== null, fn () => [
                'update' => $request->user()->can('update', $this->resource),
                'delete' => $request->user()->can('delete', $this->resource),
                'score' => $request->user()->can('score', $this->resource),
            ]),
        ];
    }
}
