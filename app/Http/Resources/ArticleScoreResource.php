<?php

namespace App\Http\Resources;

use App\Enums\ArticleScoreCategory;
use App\Models\ArticleScore;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ArticleScore */
class ArticleScoreResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'article_id' => $this->article_id,
            'score_type' => $this->score_type->value,
            'overall_score' => $this->overall_score,
            // Every category is present so the breakdown always has the same shape.
            'category_scores' => collect(ArticleScoreCategory::cases())
                ->mapWithKeys(fn (ArticleScoreCategory $category) => [$category->value => $this->category_scores[$category->value] ?? null])
                ->all(),
            'feedback' => $this->feedback,
            'scorer' => $this->whenLoaded('scorer', fn () => $this->scorer?->only(['id', 'name'])),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
