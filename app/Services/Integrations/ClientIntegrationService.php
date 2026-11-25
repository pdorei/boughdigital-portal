<?php

namespace App\Services\Integrations;

use App\Enums\ActivityEvent;
use App\Enums\IntegrationAuthType;
use App\Enums\IntegrationProvider;
use App\Enums\IntegrationStatus;
use App\Models\Client;
use App\Models\ClientIntegration;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Stores client integrations and their encrypted credentials. No provider API
 * is called here; future provider code reads $integration->credentials.
 * Activity entries record which integration changed, never secret values.
 */
class ClientIntegrationService
{
    public function __construct(private ActivityLogger $activity) {}

    public function listForClient(Client $client): Collection
    {
        return $client->integrations()
            ->orderBy('provider')
            ->orderBy('name')
            ->get()
            ->each->setRelation('client', $client);
    }

    public function findForClient(Client $client, int $id): ClientIntegration
    {
        return $client->integrations()->findOrFail($id)->setRelation('client', $client);
    }

    public function create(Client $client, User $user, array $data, string $source = 'portal'): ClientIntegration
    {
        $provider = IntegrationProvider::from($data['provider']);

        if (! $provider->isAvailable()) {
            throw ValidationException::withMessages(['provider' => "{$provider->label()} connections are not available yet."]);
        }

        $integration = DB::transaction(function () use ($client, $provider, $data): ClientIntegration {
            // Serialises concurrent creates so the duplicate check below holds.
            Client::query()->whereKey($client->id)->lockForUpdate()->first();

            if ($client->integrations()->where('provider', $provider)->exists()) {
                throw ValidationException::withMessages(['provider' => "This client already has a {$provider->label()} integration. Edit it instead."]);
            }

            return $client->integrations()->create([
                'provider' => $provider,
                'auth_type' => $provider->authType(),
                'name' => $this->name($data['name'] ?? null),
                'credentials' => $this->credentials($provider->authType(), $data['credentials'] ?? []),
                'status' => IntegrationStatus::Connected,
                'connected_at' => now(),
            ])->setRelation('client', $client);
        });

        $this->log(ActivityEvent::IntegrationConnected, $integration, $user, $source);

        return $integration;
    }

    /**
     * A blank credential keeps the saved one. Entering a credential for a
     * disconnected integration reconnects it.
     */
    public function update(ClientIntegration $integration, User $user, array $data, string $source = 'portal'): ClientIntegration
    {
        $changed = [];

        if (array_key_exists('name', $data)) {
            $integration->name = $this->name($data['name']);
            $changed = $integration->isDirty('name') ? ['name'] : [];
        }

        $enteredCredential = filled($data['credentials'][$integration->auth_type->credentialField()] ?? null);

        if ($enteredCredential) {
            $integration->credentials = $this->credentials($integration->auth_type, $data['credentials']);
            $changed[] = 'credentials';
        }

        // A rename alone does not clear an error or disconnected state; a new credential does.
        $reconnected = $integration->status !== IntegrationStatus::Connected && $enteredCredential;

        if ($reconnected) {
            $integration->forceFill(['status' => IntegrationStatus::Connected, 'connected_at' => now(), 'last_error' => null]);
        }

        $integration->save();

        if ($reconnected) {
            $this->log(ActivityEvent::IntegrationConnected, $integration, $user, $source);
        } elseif ($changed !== []) {
            $this->log(ActivityEvent::IntegrationUpdated, $integration, $user, $source, ['fields' => $changed]);
        }

        return $integration;
    }

    /**
     * Removes the stored credentials but keeps the record so it can be reconnected.
     */
    public function disconnect(ClientIntegration $integration, User $user, string $source = 'portal'): ClientIntegration
    {
        $integration->forceFill([
            'credentials' => null,
            'status' => IntegrationStatus::Disconnected,
            'connected_at' => null,
            'last_error' => null,
        ])->save();

        $this->log(ActivityEvent::IntegrationDisconnected, $integration, $user, $source);

        return $integration;
    }

    public function delete(ClientIntegration $integration, User $user, string $source = 'portal'): void
    {
        DB::transaction(function () use ($integration, $user, $source): void {
            $this->log(ActivityEvent::IntegrationDeleted, $integration, $user, $source);

            $integration->delete();
        });
    }

    private function name(?string $name): ?string
    {
        return trim((string) $name) ?: null;
    }

    private function credentials(IntegrationAuthType $authType, array $input): array
    {
        $field = $authType->credentialField();
        $value = trim((string) ($input[$field] ?? '')) ?: null;

        if ($value === null) {
            throw ValidationException::withMessages(["credentials.{$field}" => 'Enter the '.$authType->label().'.']);
        }

        return [$field => $value];
    }

    private function log(ActivityEvent $event, ClientIntegration $integration, User $user, string $source, array $details = []): void
    {
        $this->activity->log($event, $integration->client->workspace, $user, $integration, [
            'name' => $integration->displayName(),
            'provider' => $integration->provider->value,
            'auth_type' => $integration->auth_type->value,
            ...$details,
        ], $source);
    }
}
