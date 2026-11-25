<?php

namespace App\Models;

use App\Enums\ArticleScoreType;
use App\Enums\ArticleStatus;
use App\Enums\SearchIntent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['workspace_id', 'client_id', 'content_pillar_id', 'assigned_writer_id', 'title', 'slug', 'primary_keyword', 'secondary_keywords', 'search_intent', 'status', 'word_count', 'content_body', 'excerpt', 'meta_title', 'meta_description', 'canonical_url', 'published_url', 'published_at', 'tags', 'metadata'])]
class Article extends Model
{
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function pillar(): BelongsTo
    {
        return $this->belongsTo(ContentPillar::class, 'content_pillar_id');
    }

    public function writer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_writer_id');
    }

    public function calendarEntry(): HasOne
    {
        return $this->hasOne(ContentCalendarEntry::class);
    }

    public function scores(): HasMany
    {
        return $this->hasMany(ArticleScore::class);
    }

    public function latestAiScore(): HasOne
    {
        return $this->latestScoreOf(ArticleScoreType::Ai);
    }

    public function latestHumanScore(): HasOne
    {
        return $this->latestScoreOf(ArticleScoreType::Human);
    }

    private function latestScoreOf(ArticleScoreType $type): HasOne
    {
        return $this->hasOne(ArticleScore::class)->ofMany(['created_at' => 'max', 'id' => 'max'], fn ($query) => $query->where('score_type', $type));
    }

    protected function casts(): array
    {
        return [
            'status' => ArticleStatus::class,
            'search_intent' => SearchIntent::class,
            'secondary_keywords' => 'array',
            'tags' => 'array',
            'metadata' => 'array',
            'word_count' => 'integer',
            'published_at' => 'datetime',
        ];
    }
}
