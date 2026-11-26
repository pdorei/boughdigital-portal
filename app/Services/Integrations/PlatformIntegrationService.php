<?php

namespace App\Services\Integrations;

use App\Enums\ActivityEvent;
use App\Enums\IntegrationStatus;
use App\Enums\PlatformIntegrationProvider;
use App\Models\PlatformIntegration;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use Illuminate\Support\Facades\DB;

/**
 * Stores platform-level connections (Upwork, Claude) and their encrypted
 * credentials. publicStatus() is the only shape that leaves the backend: it
 * exposes configured flags, never keys or tokens. Activity entries record
 * what changed, never secret values.
 */
class PlatformIntegrationService
{
    public function __construct(private ActivityLogger $activity) {}

    public function get(PlatformIntegrationProvider $provider): PlatformIntegration
    {
        return PlatformIntegration::for($provider);
    }

    /**
     * @return array<string, mixed>
     */
    public function publicStatus(PlatformIntegrationProvider $provider): array
    {
        $integration = $this->get($provider);

        $status = [
            'provider' => $provider->value,
            'label' => $provider->label(),
            'status' => ($integration->status ?? IntegrationStatus::Disconnected)->value,
            'connected' => $integration->isConnected(),
            'credential_configured' => $integration->hasCredentials(),
            'connected_at' => $integration->connected_at?->toISOString(),
            'last_refreshed_at' => $integration->last_refreshed_at?->toISOString(),
            'last_error' => $integration->last_error,
        ];

        if ($provider === PlatformIntegrationProvider::Upwork) {
            $status['account_name'] = $integration->setting('account_name');
            $status['driver'] = config('upwork.driver');
            $status['app_configured'] = config('upwork.driver') === 'fake'
                || (filled(config('upwork.client_id')) && filled(config('upwork.client_secret')));
        }

        if ($provider === PlatformIntegrationProvider::Anthropic) {
            $status['model'] = $integration->setting('model', config('services.anthropic.default_model'));
            $status['fit_model'] = $integration->setting('fit_model', config('services.anthropic.default_fit_model'));
            $status['monthly_cap_usd'] = (float) $integration->setting('monthly_cap_usd', config('services.anthropic.default_monthly_cap_usd'));
            $usage = $integration->setting('usage.'.now()->format('Y-m'), []);
            $status['usage_this_month'] = [
                'input_tokens' => (int) ($usage['input_tokens'] ?? 0),
                'output_tokens' => (int) ($usage['output_tokens'] ?? 0),
                'cost_usd' => round((float) ($usage['cost_usd'] ?? 0), 4),
            ];
            $status['models'] = collect(config('services.anthropic.models'))
                ->map(fn (array $model, string $id) => ['value' => $id, 'label' => $model['label']])
                ->values()
                ->all();
        }

        return $status;
    }

    /**
     * Saves OAuth tokens after a successful consent (or a token refresh).
     *
     * @param  array{access_token: string, refresh_token: ?string, expires_at: ?int}  $tokens
     */
    public function storeUpworkTokens(array $tokens, ?User $user = null, ?string $accountName = null, bool $refresh = false): PlatformIntegration
    {
        $integration = $this->get(PlatformIntegrationProvider::Upwork);
        $wasConnected = $integration->isConnected();

        $integration->forceFill([
            'credentials' => $tokens,
            'status' => IntegrationStatus::Connected,
            'last_refreshed_at' => now(),
            'last_error' => null,
        ]);

        if (! $refresh) {
            $integration->connected_at = now();
            $integration->connected_by = $user?->id;
            $integration->settings = [...($integration->settings ?? []), 'account_name' => $accountName];
        }

        $integration->save();

        if (! $refresh && $user) {
            $this->activity->log(ActivityEvent::UpworkConnected, null, $user, $integration, [
                'provider' => 'upwork',
                'account_name' => $accountName,
                'reconnected' => $wasConnected,
            ]);
        }

        return $integration;
    }

    /**
     * A blank API key keeps the saved one.
     *
     * @param  array{api_key?: ?string, model?: string, fit_model?: string, monthly_cap_usd?: float|int|string}  $data
     */
    public function updateAnthropic(User $user, array $data): PlatformIntegration
    {
        $integration = $this->get(PlatformIntegrationProvider::Anthropic);
        $changed = [];

        $apiKey = trim((string) ($data['api_key'] ?? ''));
        if ($apiKey !== '') {
            $integration->credentials = ['api_key' => $apiKey];
            $integration->status = IntegrationStatus::Connected;
            $integration->connected_at = now();
            $integration->connected_by = $user->id;
            $integration->last_error = null;
            $changed[] = 'api_key';
        }

        $settings = $integration->settings ?? [];
        foreach (['model', 'fit_model', 'monthly_cap_usd'] as $field) {
            if (array_key_exists($field, $data) && ($settings[$field] ?? null) != $data[$field]) {
                $settings[$field] = $field === 'monthly_cap_usd' ? (float) $data[$field] : $data[$field];
                $changed[] = $field;
            }
        }
        $integration->settings = $settings;
        $integration->status ??= IntegrationStatus::Disconnected;
        $integration->save();

        if ($changed !== []) {
            $this->activity->log(ActivityEvent::PlatformIntegrationUpdated, null, $user, $integration, [
                'provider' => 'anthropic',
                'fields' => $changed,
            ]);
        }

        return $integration;
    }

    /**
     * Clears the credentials but keeps the record (and Claude's usage history).
     */
    public function disconnect(PlatformIntegrationProvider $provider, User $user): PlatformIntegration
    {
        $integration = $this->get($provider);

        $integration->forceFill([
            'credentials' => null,
            'status' => IntegrationStatus::Disconnected,
            'connected_at' => null,
            'last_error' => null,
        ])->save();

        $this->activity->log(
            $provider === PlatformIntegrationProvider::Upwork ? ActivityEvent::UpworkDisconnected : ActivityEvent::PlatformIntegrationUpdated,
            null,
            $user,
            $integration,
            ['provider' => $provider->value, 'disconnected' => true],
        );

        return $integration;
    }

    public function markError(PlatformIntegrationProvider $provider, string $message): void
    {
        $integration = $this->get($provider);

        if (! $integration->exists) {
            return;
        }

        $integration->forceFill(['status' => IntegrationStatus::Error, 'last_error' => mb_substr($message, 0, 500)])->save();
    }

    /**
     * Adds token usage to the month's total under a row lock, so parallel
     * queue workers do not overwrite each other's counts.
     */
    public function recordAnthropicUsage(int $inputTokens, int $outputTokens, float $costUsd): void
    {
        DB::transaction(function () use ($inputTokens, $outputTokens, $costUsd): void {
            $integration = PlatformIntegration::query()
                ->where('provider', PlatformIntegrationProvider::Anthropic->value)
                ->lockForUpdate()
                ->first();

            if (! $integration) {
                return;
            }

            $month = now()->format('Y-m');
            $settings = $integration->settings ?? [];
            $usage = $settings['usage'][$month] ?? [];

            $settings['usage'][$month] = [
                'input_tokens' => (int) ($usage['input_tokens'] ?? 0) + $inputTokens,
                'output_tokens' => (int) ($usage['output_tokens'] ?? 0) + $outputTokens,
                'cost_usd' => round((float) ($usage['cost_usd'] ?? 0) + $costUsd, 6),
            ];

            $integration->settings = $settings;
            $integration->save();
        });
    }

    public function anthropicSpendThisMonth(): float
    {
        return (float) $this->get(PlatformIntegrationProvider::Anthropic)->setting('usage.'.now()->format('Y-m').'.cost_usd', 0);
    }
}
