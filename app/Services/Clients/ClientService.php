<?php

namespace App\Services\Clients;

use App\Enums\ActivityEvent;
use App\Enums\ClientStatus;
use App\Enums\TaskStatus;
use App\Models\Client;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Audit\ActivityLogger;
use App\Support\Pagination;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ClientService
{
    private const AUDITED_FIELDS = ['name', 'slug', 'website_url', 'status'];

    public function __construct(private ActivityLogger $activity) {}

    public function list(Workspace $workspace, User $user, array $filters = []): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $status = ClientStatus::tryFrom((string) ($filters['status'] ?? ''));

        $query = Client::query()
            ->whereBelongsTo($workspace)
            ->visibleTo($user)
            ->withCount([
                'documents',
                'tasks',
                'tasks as open_tasks_count' => fn ($query) => $query->where('status', '!=', TaskStatus::Completed),
            ])
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->whereLike('name', "%{$search}%")
                ->orWhereLike('website_url', "%{$search}%")))
            ->when($status, fn ($query) => $query->where('status', $status))
            ->orderBy('name');

        return Pagination::paginate($query, $filters);
    }

    public function findInWorkspace(Workspace $workspace, int $id): Client
    {
        return Client::query()->whereBelongsTo($workspace)->findOrFail($id);
    }

    public function findBySlug(Workspace $workspace, string $slug): Client
    {
        return Client::query()->whereBelongsTo($workspace)->where('slug', $slug)->firstOrFail();
    }

    public function create(Workspace $workspace, User $user, array $data, string $source = 'portal'): Client
    {
        $client = Client::query()->create([
            'workspace_id' => $workspace->id,
            'name' => $data['name'],
            'slug' => $this->slugFor($workspace, $data['slug'] ?? null, $data['name']),
            'website_url' => $data['website_url'] ?? null,
            'status' => $data['status'] ?? ClientStatus::Active,
            'metadata' => $data['metadata'] ?? [],
        ]);

        $this->activity->log(ActivityEvent::ClientCreated, $workspace, $user, $client, $this->auditSnapshot($client), $source);

        return $client;
    }

    public function update(Client $client, User $user, array $data, string $source = 'portal'): Client
    {
        $before = $this->auditSnapshot($client);

        if (filled($data['slug'] ?? null)) {
            $data['slug'] = $this->slugFor($client->workspace, $data['slug'], $client->name, $client->id);
        } else {
            unset($data['slug']);
        }

        $client->fill(Arr::only($data, ['name', 'slug', 'website_url', 'status', 'metadata']));
        $statusChanged = $client->isDirty('status');
        $client->save();

        $this->activity->log($this->updateEvent($client, $statusChanged), $client->workspace, $user, $client, [
            'name' => $client->name,
            'before' => $before,
            'after' => $this->auditSnapshot($client),
        ], $source);

        return $client;
    }

    /**
     * Only clients without delivery history can be deleted; everything else is
     * archived so tasks, documents and activity stay intact.
     */
    public function delete(Client $client, User $user, string $source = 'portal'): void
    {
        if ($client->tasks()->exists() || $client->documents()->exists()) {
            throw ValidationException::withMessages([
                'client' => 'This client has tasks or documents. Archive it instead so its history is kept.',
            ]);
        }

        DB::transaction(function () use ($client, $user, $source): void {
            $this->activity->log(ActivityEvent::ClientDeleted, $client->workspace, $user, $client, $this->auditSnapshot($client), $source);

            $client->delete();
        });
    }

    private function updateEvent(Client $client, bool $statusChanged): ActivityEvent
    {
        if (! $statusChanged) {
            return ActivityEvent::ClientUpdated;
        }

        return $client->status === ClientStatus::Archived ? ActivityEvent::ClientArchived : ActivityEvent::ClientRestored;
    }

    private function slugFor(Workspace $workspace, ?string $requested, string $name, ?int $ignoreId = null): string
    {
        if (filled($requested)) {
            $slug = Str::slug($requested);

            if ($slug === '' || $this->slugTaken($workspace, $slug, $ignoreId)) {
                throw ValidationException::withMessages([
                    'slug' => $slug === '' ? 'The slug must contain letters or numbers.' : 'That slug is already used by another client in this workspace.',
                ]);
            }

            return $slug;
        }

        $base = Str::slug($name) ?: 'client';
        $slug = $base;

        for ($suffix = 2; $this->slugTaken($workspace, $slug, $ignoreId); $suffix++) {
            $slug = "{$base}-{$suffix}";
        }

        return $slug;
    }

    private function slugTaken(Workspace $workspace, string $slug, ?int $ignoreId): bool
    {
        return Client::query()
            ->whereBelongsTo($workspace)
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();
    }

    private function auditSnapshot(Client $client): array
    {
        return collect($client->only(self::AUDITED_FIELDS))
            ->map(fn ($value) => $value instanceof ClientStatus ? $value->value : $value)
            ->all();
    }
}
