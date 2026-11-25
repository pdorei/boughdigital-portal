<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['workspace_id', 'client_id', 'name', 'description', 'color', 'sort_order', 'active'])]
class ContentPillar extends Model
{
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    public function calendarEntries(): HasMany
    {
        return $this->hasMany(ContentCalendarEntry::class);
    }

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
