<?php

namespace App\Models;

use App\Enums\ContentCalendarStatus;
use App\Enums\ContentType;
use App\Enums\SearchIntent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['workspace_id', 'client_id', 'content_pillar_id', 'article_id', 'assigned_writer_id', 'title', 'target_keyword', 'secondary_keywords', 'status', 'content_type', 'search_intent', 'due_date', 'publish_date', 'notes', 'performance_data'])]
class ContentCalendarEntry extends Model
{
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function pillar(): BelongsTo
    {
        return $this->belongsTo(ContentPillar::class, 'content_pillar_id');
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function writer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_writer_id');
    }

    protected function casts(): array
    {
        return [
            'status' => ContentCalendarStatus::class,
            'content_type' => ContentType::class,
            'search_intent' => SearchIntent::class,
            'secondary_keywords' => 'array',
            'performance_data' => 'array',
            'due_date' => 'date',
            'publish_date' => 'date',
        ];
    }
}
