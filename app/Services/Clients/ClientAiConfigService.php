<?php

namespace App\Services\Clients;

use App\Enums\ActivityEvent;
use App\Models\Client;
use App\Models\ClientAiConfig;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use Illuminate\Support\Arr;

/**
 * One AI configuration per client. The portal, the REST API and a future
 * set_client_ai_config MCP tool all save through here, and future AI tools can
 * read a client's context with forClient().
 */
class ClientAiConfigService
{
    public function __construct(private ActivityLogger $activity) {}

    /**
     * Returns the saved configuration, or an unsaved empty one when the
     * client has not been configured yet.
     */
    public function forClient(Client $client): ClientAiConfig
    {
        $config = $client->aiConfig ?? new ClientAiConfig(['client_id' => $client->id]);

        return $config->setRelation('client', $client);
    }

    /**
     * Upserts the configuration. Only the supplied fields change, so callers
     * can update a single setting without resending the rest.
     */
    public function save(Client $client, User $user, array $data, string $source = 'portal'): ClientAiConfig
    {
        // createOrFirst relies on the unique client_id index, so two
        // simultaneous first saves still end up with a single row.
        $config = $client->aiConfig()->createOrFirst();
        $config->fill($this->prepare($data));

        $changed = array_keys(Arr::except($config->getDirty(), ['updated_at']));
        $config->save();

        if ($changed !== [] || $config->wasRecentlyCreated) {
            $this->activity->log(ActivityEvent::ClientAiConfigUpdated, $client->workspace, $user, $config, [
                'name' => $client->name,
                'fields' => $changed,
            ], $source);
        }

        $client->setRelation('aiConfig', $config);

        return $config->setRelation('client', $client);
    }

    public function reset(Client $client, User $user, string $source = 'portal'): void
    {
        $config = $client->aiConfig;

        if (! $config) {
            return;
        }

        $config->delete();
        $client->setRelation('aiConfig', null);

        $this->activity->log(ActivityEvent::ClientAiConfigReset, $client->workspace, $user, $config, ['name' => $client->name], $source);
    }

    private function prepare(array $data): array
    {
        $attributes = Arr::only($data, ClientAiConfig::FIELDS);

        foreach (Arr::except($attributes, 'key_urls') as $field => $value) {
            $attributes[$field] = filled($value) ? trim((string) $value) : null;
        }

        if (array_key_exists('key_urls', $attributes)) {
            $urls = collect($attributes['key_urls'] ?? [])
                ->map(fn (array $entry) => ['label' => trim((string) $entry['label']), 'url' => trim((string) $entry['url'])])
                ->values()
                ->all();

            $attributes['key_urls'] = $urls === [] ? null : $urls;
        }

        return $attributes;
    }
}
