<?php

namespace App\Enums;

enum IntegrationAuthType: string
{
    case OAuth = 'oauth';
    case ApiKey = 'api_key';
    case AccessToken = 'access_token';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::OAuth => 'OAuth',
            self::ApiKey => 'API key',
            self::AccessToken => 'Access token',
            self::Other => 'Other credential',
        };
    }

    /**
     * The secret a person enters for this method. OAuth has none: its tokens
     * will come from the provider's consent flow.
     */
    public function credentialField(): ?string
    {
        return match ($this) {
            self::OAuth => null,
            self::ApiKey => 'api_key',
            self::AccessToken => 'access_token',
            self::Other => 'secret',
        };
    }

    /**
     * @return array<int, array{value: string, label: string, credential_field: ?string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $type) => [
            'value' => $type->value,
            'label' => $type->label(),
            'credential_field' => $type->credentialField(),
        ], self::cases());
    }
}
