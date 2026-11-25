<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['client_id', 'brand_tone', 'writing_preferences', 'industry_context', 'key_urls', 'custom_instructions'])]
class ClientAiConfig extends Model
{
    public const FIELDS = ['brand_tone', 'writing_preferences', 'industry_context', 'key_urls', 'custom_instructions'];

    public const MAX_KEY_URLS = 25;

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    protected function casts(): array
    {
        return [
            'key_urls' => 'array',
        ];
    }
}
