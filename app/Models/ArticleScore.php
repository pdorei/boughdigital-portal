<?php

namespace App\Models;

use App\Enums\ArticleScoreType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['workspace_id', 'article_id', 'scored_by_user_id', 'score_type', 'overall_score', 'category_scores', 'feedback'])]
class ArticleScore extends Model
{
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function scorer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scored_by_user_id');
    }

    protected function casts(): array
    {
        return [
            'score_type' => ArticleScoreType::class,
            'overall_score' => 'integer',
            'category_scores' => 'array',
        ];
    }
}
