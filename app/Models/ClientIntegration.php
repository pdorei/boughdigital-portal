<?php

namespace App\Models;

use App\Enums\IntegrationAuthType;
use App\Enums\IntegrationProvider;
use App\Enums\IntegrationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Credentials are encrypted with the application key before they are stored
 * and are hidden from serialization; read them only in backend integration code.
 */
#[Fillable(['client_id', 'provider', 'auth_type', 'name', 'credentials', 'settings', 'status', 'connected_at', 'last_error'])]
#[Hidden(['credentials'])]
class ClientIntegration extends Model
{
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Checks the stored ciphertext, so no decryption is needed to show status.
     */
    public function hasCredentials(): bool
    {
        return filled($this->getRawOriginal('credentials'));
    }

    public function displayName(): string
    {
        return $this->name ?: $this->provider->label();
    }

    protected function casts(): array
    {
        return [
            'provider' => IntegrationProvider::class,
            'auth_type' => IntegrationAuthType::class,
            'status' => IntegrationStatus::class,
            'credentials' => 'encrypted:array',
            'settings' => 'array',
            'connected_at' => 'datetime',
            'last_used_at' => 'datetime',
            'last_verified_at' => 'datetime',
        ];
    }
}
