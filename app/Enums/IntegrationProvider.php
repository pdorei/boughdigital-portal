<?php

namespace App\Enums;

/**
 * The catalogue of services a client can be connected to. No provider API is
 * called yet: a provider is "available" once its credentials can be saved.
 * Implementing a provider later means enabling it here and adding the code
 * that uses the stored credentials.
 */
enum IntegrationProvider: string
{
    case GoogleAnalytics = 'google_analytics';
    case GoogleSearchConsole = 'google_search_console';
    case Semrush = 'semrush';
    case Facebook = 'facebook';
    case Instagram = 'instagram';
    case LinkedIn = 'linkedin';
    case YouTube = 'youtube';
    case X = 'x';

    public function label(): string
    {
        return match ($this) {
            self::GoogleAnalytics => 'Google Analytics 4',
            self::GoogleSearchConsole => 'Google Search Console',
            self::Semrush => 'Semrush',
            self::Facebook => 'Facebook',
            self::Instagram => 'Instagram',
            self::LinkedIn => 'LinkedIn',
            self::YouTube => 'YouTube',
            self::X => 'X',
        };
    }

    public function authType(): IntegrationAuthType
    {
        return match ($this) {
            self::Semrush => IntegrationAuthType::ApiKey,
            default => IntegrationAuthType::OAuth,
        };
    }

    /**
     * OAuth consent flows are not built yet, so those providers cannot be saved.
     */
    public function isAvailable(): bool
    {
        return $this->authType() !== IntegrationAuthType::OAuth;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function options(): array
    {
        return array_map(fn (self $provider) => [
            'value' => $provider->value,
            'label' => $provider->label(),
            'auth_type' => $provider->authType()->value,
            'available' => $provider->isAvailable(),
        ], self::cases());
    }
}
