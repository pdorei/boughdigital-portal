<?php

namespace App\Models;

use App\Enums\CompetitorTier;
use App\Enums\CompetitorType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Workspace isolation comes from the owning client; there is no workspace_id column.
 */
#[Fillable(['client_id', 'competitor_name', 'competitor_domain', 'description', 'industry', 'notes', 'contact', 'social_media', 'keywords', 'location', 'competitor_type', 'tier'])]
class ClientCompetitor extends Model
{
    public const CONTACT_FIELDS = ['name', 'email', 'phone'];

    public const SOCIAL_NETWORKS = ['facebook', 'instagram', 'linkedin', 'youtube', 'x'];

    public const MAX_KEYWORDS = 50;

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function hasLogo(): bool
    {
        return filled($this->logo_path);
    }

    protected function casts(): array
    {
        return [
            'contact' => 'array',
            'social_media' => 'array',
            'keywords' => 'array',
            'competitor_type' => CompetitorType::class,
            'tier' => CompetitorTier::class,
        ];
    }
}
