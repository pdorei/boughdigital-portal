<?php

namespace App\Services\Content;

use App\Enums\ActivityEvent;
use App\Models\Client;
use App\Models\ContentPillar;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ContentPillarService
{
    private const EDITABLE_FIELDS = ['name', 'description', 'color', 'sort_order', 'active'];

    public function __construct(private ActivityLogger $activity) {}

    public function listForClient(Client $client): Collection
    {
        return $client->contentPillars()
            ->withCount(['articles', 'calendarEntries'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->each->setRelation('client', $client);
    }

    public function findForClient(Client $client, int $id): ContentPillar
    {
        return $client->contentPillars()->findOrFail($id)->setRelation('client', $client);
    }

    public function create(Client $client, User $user, array $data, string $source = 'portal'): ContentPillar
    {
        $this->ensureNameIsUnique($client, $data['name']);

        $pillar = $client->contentPillars()->create([
            ...Arr::only($data, self::EDITABLE_FIELDS),
            'workspace_id' => $client->workspace_id,
            'name' => trim($data['name']),
            'active' => $data['active'] ?? true,
            'sort_order' => $data['sort_order'] ?? (int) $client->contentPillars()->max('sort_order') + 1,
        ])->setRelation('client', $client);

        $this->log(ActivityEvent::ContentPillarCreated, $pillar, $user, $source);

        return $pillar;
    }

    public function update(ContentPillar $pillar, User $user, array $data, string $source = 'portal'): ContentPillar
    {
        if (array_key_exists('name', $data)) {
            $data['name'] = trim($data['name']);
            $this->ensureNameIsUnique($pillar->client, $data['name'], $pillar->id);
        }

        $pillar->fill(Arr::only($data, self::EDITABLE_FIELDS));
        $changed = array_keys($pillar->getDirty());
        $pillar->save();

        if ($changed !== []) {
            $this->log(ActivityEvent::ContentPillarUpdated, $pillar, $user, $source, ['fields' => $changed]);
        }

        return $pillar;
    }

    /**
     * A pillar that articles or calendar entries use is deactivated rather than
     * deleted, so existing content keeps its pillar.
     */
    public function delete(ContentPillar $pillar, User $user, string $source = 'portal'): void
    {
        $articles = $pillar->articles()->count();
        $entries = $pillar->calendarEntries()->count();

        if ($articles + $entries > 0) {
            $usage = collect([
                $articles ? $articles.' '.Str::plural('article', $articles) : null,
                $entries ? $entries.' '.Str::plural('calendar entry', $entries) : null,
            ])->filter()->join(' and ');

            throw ValidationException::withMessages([
                'pillar' => "This pillar is used by {$usage}. Deactivate it instead so that content keeps its pillar.",
            ]);
        }

        DB::transaction(function () use ($pillar, $user, $source): void {
            $this->log(ActivityEvent::ContentPillarDeleted, $pillar, $user, $source);

            $pillar->delete();
        });
    }

    private function ensureNameIsUnique(Client $client, string $name, ?int $ignoreId = null): void
    {
        $taken = $client->contentPillars()
            ->whereRaw('lower(name) = ?', [mb_strtolower(trim($name))])
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages(['name' => 'This client already has a pillar with that name.']);
        }
    }

    private function log(ActivityEvent $event, ContentPillar $pillar, User $user, string $source, array $details = []): void
    {
        $this->activity->log($event, $pillar->client->workspace, $user, $pillar, ['name' => $pillar->name, ...$details], $source);
    }
}
