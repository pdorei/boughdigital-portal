<?php

namespace App\Models;

use App\Enums\IntegrationStatus;
use App\Enums\PlatformIntegrationProvider;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

/**
 * A platform-level service connection (one row per provider). Credentials are
 * encrypted with the application key and hidden from serialization; read them
 * only in backend provider code (UpworkOAuthService, ClaudeClient).
 */
#[Fillable(['provider', 'status', 'credentials', 'settings', 'connected_at', 'last_refreshed_at', 'last_error', 'connected_by'])]
#[Hidden(['credentials'])]
class PlatformIntegration extends Model
{
    public static function for(PlatformIntegrationProvider $provider): self
    {
        return self::query()->firstOrNew(
            ['provider' => $provider->value],
            ['status' => IntegrationStatus::Disconnected],
        );
    }

    /**
     * Checks the stored ciphertext, so no decryption is needed to show status.
     */
    public function hasCredentials(): bool
    {
        return filled($this->getRawOriginal('credentials'));
    }

    public function isConnected(): bool
    {
        return $this->exists && $this->status === IntegrationStatus::Connected && $this->hasCredentials();
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings ?? [], $key, $default);
    }

    protected function casts(): array
    {
        return [
            'provider' => PlatformIntegrationProvider::class,
            'status' => IntegrationStatus::class,
            'credentials' => 'encrypted:array',
            'settings' => 'array',
            'connected_at' => 'datetime',
            'last_refreshed_at' => 'datetime',
        ];
    }
}
