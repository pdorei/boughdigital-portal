<?php

namespace App\Enums;

/**
 * Platform-level services (Super Admin, no workspace). Client-level providers
 * live in IntegrationProvider.
 */
enum PlatformIntegrationProvider: string
{
    case Upwork = 'upwork';
    case Anthropic = 'anthropic';

    public function label(): string
    {
        return match ($this) {
            self::Upwork => 'Upwork',
            self::Anthropic => 'Claude (Anthropic)',
        };
    }

    public function authType(): IntegrationAuthType
    {
        return match ($this) {
            self::Upwork => IntegrationAuthType::OAuth,
            self::Anthropic => IntegrationAuthType::ApiKey,
        };
    }

    /**
     * @return array<int, array<string, string>>
     */
    public static function options(): array
    {
        return array_map(fn (self $provider) => [
            'value' => $provider->value,
            'label' => $provider->label(),
            'auth_type' => $provider->authType()->value,
        ], self::cases());
    }
}
