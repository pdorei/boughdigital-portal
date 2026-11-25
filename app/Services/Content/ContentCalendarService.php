<?php

namespace App\Services\Content;

use App\Enums\ActivityEvent;
use App\Enums\ContentCalendarStatus;
use App\Enums\ContentType;
use App\Models\Client;
use App\Models\ContentCalendarEntry;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use App\Services\Content\Concerns\ChecksContentRelations;
use App\Support\KeywordList;
use App\Support\Pagination;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ContentCalendarService
{
    use ChecksContentRelations;

    private const EDITABLE_FIELDS = [
        'title', 'target_keyword', 'secondary_keywords', 'content_pillar_id', 'article_id', 'assigned_writer_id',
        'status', 'content_type', 'search_intent', 'due_date', 'publish_date', 'notes',
    ];

    private const RELATIONS = ['pillar:id,name,color', 'writer:id,name', 'article:id,title,status'];

    public function __construct(private ActivityLogger $activity) {}

    public function list(Client $client, User $user, array $filters = []): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $status = ContentCalendarStatus::tryFrom((string) ($filters['status'] ?? ''));
        $writer = ($filters['writer'] ?? null) === 'me' ? $user->id : ($filters['writer'] ?? null);

        $query = $client->calendarEntries()
            ->with(self::RELATIONS)
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->whereLike('title', "%{$search}%")
                ->orWhereLike('target_keyword', "%{$search}%")))
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($writer, fn ($query) => $query->where('assigned_writer_id', (int) $writer))
            ->when($filters['pillar'] ?? null, fn ($query, $pillar) => $query->where('content_pillar_id', (int) $pillar))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('publish_date', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->whereDate('publish_date', '<=', $to))
            ->orderByRaw('publish_date is null, publish_date asc')
            ->orderByRaw('due_date is null, due_date asc')
            ->orderBy('id');

        return Pagination::paginate($query, $filters);
    }

    public function findForClient(Client $client, int $id): ContentCalendarEntry
    {
        return $client->calendarEntries()->with(self::RELATIONS)->findOrFail($id)->setRelation('client', $client);
    }

    public function create(Client $client, User $user, array $data, string $source = 'portal'): ContentCalendarEntry
    {
        $entry = $client->calendarEntries()->create([
            ...$this->prepare($client, $data),
            'workspace_id' => $client->workspace_id,
            'status' => $data['status'] ?? ContentCalendarStatus::Planned,
            'content_type' => $data['content_type'] ?? ContentType::Article,
        ])->setRelation('client', $client);

        $this->log(ActivityEvent::CalendarEntryCreated, $entry, $user, $source, ['status' => $entry->status->value]);

        return $entry->load(self::RELATIONS);
    }

    public function update(ContentCalendarEntry $entry, User $user, array $data, string $source = 'portal'): ContentCalendarEntry
    {
        $entry->fill($this->prepare($entry->client, $data, $entry));
        $changed = array_keys($entry->getDirty());
        $entry->save();

        if ($changed !== []) {
            $this->log(ActivityEvent::CalendarEntryUpdated, $entry, $user, $source, ['fields' => $changed]);
        }

        return $entry->load(self::RELATIONS);
    }

    public function delete(ContentCalendarEntry $entry, User $user, string $source = 'portal'): void
    {
        DB::transaction(function () use ($entry, $user, $source): void {
            $this->log(ActivityEvent::CalendarEntryDeleted, $entry, $user, $source);

            $entry->delete();
        });
    }

    private function prepare(Client $client, array $data, ?ContentCalendarEntry $entry = null): array
    {
        $attributes = Arr::only($data, self::EDITABLE_FIELDS);

        if (array_key_exists('content_pillar_id', $attributes)) {
            $attributes['content_pillar_id'] = $this->pillarId($client, $attributes['content_pillar_id']);
        }

        if (array_key_exists('assigned_writer_id', $attributes)) {
            $attributes['assigned_writer_id'] = $this->writerId($client, $attributes['assigned_writer_id']);
        }

        if (array_key_exists('article_id', $attributes)) {
            $attributes['article_id'] = $this->articleId($client, $attributes['article_id'], $entry);
        }

        if (array_key_exists('secondary_keywords', $attributes)) {
            $attributes['secondary_keywords'] = KeywordList::clean($attributes['secondary_keywords']);
        }

        return $attributes;
    }

    /**
     * An article can fulfil only one calendar entry.
     */
    private function articleId(Client $client, mixed $id, ?ContentCalendarEntry $entry): ?int
    {
        if (blank($id)) {
            return null;
        }

        if (! $client->articles()->whereKey($id)->exists()) {
            throw ValidationException::withMessages(['article_id' => 'Choose an article of this client.']);
        }

        $linkedElsewhere = $client->calendarEntries()
            ->where('article_id', $id)
            ->when($entry, fn ($query) => $query->whereKeyNot($entry->id))
            ->exists();

        if ($linkedElsewhere) {
            throw ValidationException::withMessages(['article_id' => 'That article is already linked to another calendar entry.']);
        }

        return (int) $id;
    }

    private function log(ActivityEvent $event, ContentCalendarEntry $entry, User $user, string $source, array $details = []): void
    {
        $this->activity->log($event, $entry->client->workspace, $user, $entry, ['title' => $entry->title, ...$details], $source);
    }
}
