<?php

namespace App\Services\Competitors;

use App\Enums\ActivityEvent;
use App\Models\Client;
use App\Models\ClientCompetitor;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use App\Services\Workspaces\WorkspaceStorageService;
use App\Support\DomainName;
use App\Support\KeywordList;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class CompetitorService
{
    public const MAX_PER_CLIENT = 10;

    private const EDITABLE_FIELDS = [
        'competitor_name', 'competitor_domain', 'description', 'industry', 'notes',
        'contact', 'social_media', 'keywords', 'location', 'competitor_type', 'tier',
    ];

    public function __construct(private ActivityLogger $activity, private WorkspaceStorageService $storage) {}

    public function listForClient(Client $client): Collection
    {
        return $client->competitors()
            ->orderBy('tier')
            ->orderBy('competitor_name')
            ->get()
            ->each->setRelation('client', $client);
    }

    public function findForClient(Client $client, int $id): ClientCompetitor
    {
        return $client->competitors()->findOrFail($id)->setRelation('client', $client);
    }

    public function create(Client $client, User $user, array $data, string $source = 'portal'): ClientCompetitor
    {
        $attributes = $this->prepare($data);

        $competitor = DB::transaction(function () use ($client, $attributes): ClientCompetitor {
            // Locking the client row serialises concurrent creates, so two
            // requests cannot both pass the limit or duplicate check.
            Client::query()->whereKey($client->id)->lockForUpdate()->first();

            if ($client->competitors()->count() >= self::MAX_PER_CLIENT) {
                throw ValidationException::withMessages([
                    'competitors' => 'A client can have up to '.self::MAX_PER_CLIENT.' competitors. Remove one before adding another.',
                ]);
            }

            $this->ensureDomainIsUnique($client, $attributes['competitor_domain']);

            return $client->competitors()->create($attributes)->setRelation('client', $client);
        });

        $this->activity->log(ActivityEvent::CompetitorCreated, $client->workspace, $user, $competitor, [
            'name' => $competitor->competitor_name,
            'domain' => $competitor->competitor_domain,
            'tier' => $competitor->tier->value,
        ], $source);

        return $competitor;
    }

    public function update(ClientCompetitor $competitor, User $user, array $data, string $source = 'portal'): ClientCompetitor
    {
        $attributes = $this->prepare($data);

        if (array_key_exists('competitor_domain', $attributes)) {
            $this->ensureDomainIsUnique($competitor->client, $attributes['competitor_domain'], $competitor->id);
        }

        $competitor->fill($attributes);
        $changed = array_keys($competitor->getDirty());
        $competitor->save();

        if ($changed !== []) {
            $this->logUpdate($competitor, $user, $changed, $source);
        }

        return $competitor;
    }

    public function delete(ClientCompetitor $competitor, User $user, string $source = 'portal'): void
    {
        DB::transaction(function () use ($competitor, $user, $source): void {
            $this->activity->log(ActivityEvent::CompetitorDeleted, $competitor->client->workspace, $user, $competitor, [
                'name' => $competitor->competitor_name,
                'domain' => $competitor->competitor_domain,
            ], $source);

            $competitor->delete();
        });

        $this->storage->delete($competitor->logo_disk, $competitor->logo_path);
    }

    /**
     * The new file is stored and recorded before the old one is removed, so a
     * failure at any step leaves the current logo in place.
     */
    public function replaceLogo(ClientCompetitor $competitor, User $user, UploadedFile $file, string $source = 'portal'): ClientCompetitor
    {
        $previous = ['disk' => $competitor->logo_disk, 'path' => $competitor->logo_path];
        $stored = $this->storage->storeCompetitorLogo($competitor, $file);

        try {
            $competitor->forceFill(['logo_disk' => $stored['disk'], 'logo_path' => $stored['path']])->save();
        } catch (Throwable $exception) {
            $this->storage->delete($stored['disk'], $stored['path']);

            throw $exception;
        }

        $this->storage->delete($previous['disk'], $previous['path']);
        $this->logUpdate($competitor, $user, ['logo'], $source);

        return $competitor;
    }

    public function removeLogo(ClientCompetitor $competitor, User $user, string $source = 'portal'): ClientCompetitor
    {
        if (! $competitor->hasLogo()) {
            return $competitor;
        }

        $previous = ['disk' => $competitor->logo_disk, 'path' => $competitor->logo_path];

        $competitor->forceFill(['logo_disk' => null, 'logo_path' => null])->save();
        $this->storage->delete($previous['disk'], $previous['path']);
        $this->logUpdate($competitor, $user, ['logo'], $source);

        return $competitor;
    }

    public function logo(ClientCompetitor $competitor): Response
    {
        abort_unless($competitor->hasLogo(), 404);

        return $this->storage->inline($competitor->logo_disk, $competitor->logo_path);
    }

    /**
     * Normalises only the fields that were supplied, so partial updates leave
     * everything else untouched.
     */
    private function prepare(array $data): array
    {
        $attributes = Arr::only($data, self::EDITABLE_FIELDS);

        if (array_key_exists('competitor_domain', $attributes)) {
            $attributes['competitor_domain'] = DomainName::normalize($attributes['competitor_domain'])
                ?? throw ValidationException::withMessages(['competitor_domain' => 'Enter a valid domain, for example example.com.']);
        }

        if (array_key_exists('contact', $attributes)) {
            $attributes['contact'] = $this->filledOnly($attributes['contact'], ClientCompetitor::CONTACT_FIELDS);
        }

        if (array_key_exists('social_media', $attributes)) {
            $attributes['social_media'] = $this->filledOnly($attributes['social_media'], ClientCompetitor::SOCIAL_NETWORKS);
        }

        if (array_key_exists('keywords', $attributes)) {
            $attributes['keywords'] = KeywordList::clean($attributes['keywords']);
        }

        return $attributes;
    }

    private function ensureDomainIsUnique(Client $client, string $domain, ?int $ignoreId = null): void
    {
        $taken = $client->competitors()
            ->where('competitor_domain', $domain)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages(['competitor_domain' => "{$domain} is already a competitor of this client."]);
        }
    }

    /**
     * Keeps the known keys that have a value, in their canonical order.
     */
    private function filledOnly(?array $values, array $keys): ?array
    {
        $filled = collect($keys)
            ->mapWithKeys(fn (string $key) => [$key => trim((string) ($values[$key] ?? ''))])
            ->filter(fn (string $value) => $value !== '')
            ->all();

        return $filled === [] ? null : $filled;
    }

    private function logUpdate(ClientCompetitor $competitor, User $user, array $fields, string $source): void
    {
        $this->activity->log(ActivityEvent::CompetitorUpdated, $competitor->client->workspace, $user, $competitor, [
            'name' => $competitor->competitor_name,
            'fields' => array_values(array_diff($fields, ['updated_at'])),
        ], $source);
    }
}
