<?php

namespace App\Services\Content;

use App\Enums\ActivityEvent;
use App\Enums\ArticleScoreCategory;
use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\ArticleScore;
use App\Models\Client;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use App\Services\Content\Concerns\ChecksContentRelations;
use App\Support\KeywordList;
use App\Support\Pagination;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Articles and their score history. Scores are recorded, not calculated: no AI
 * scoring engine exists yet, and future tools can call addScore() directly.
 */
class ArticleService
{
    use ChecksContentRelations;

    private const EDITABLE_FIELDS = [
        'title', 'slug', 'content_pillar_id', 'assigned_writer_id', 'status', 'primary_keyword', 'secondary_keywords',
        'search_intent', 'meta_title', 'meta_description', 'canonical_url', 'content_body', 'excerpt', 'word_count',
        'published_url', 'published_at', 'tags',
    ];

    private const RELATIONS = ['pillar:id,name,color', 'writer:id,name', 'latestAiScore', 'latestHumanScore'];

    public function __construct(private ActivityLogger $activity) {}

    /**
     * The body is left out of listings; it is only needed on the article itself.
     */
    public function list(Client $client, User $user, array $filters = []): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $status = ArticleStatus::tryFrom((string) ($filters['status'] ?? ''));
        $writer = ($filters['writer'] ?? null) === 'me' ? $user->id : ($filters['writer'] ?? null);
        $columns = array_diff(array_merge(['id', 'workspace_id', 'client_id', 'created_at', 'updated_at'], self::EDITABLE_FIELDS), ['content_body']);

        $query = $client->articles()
            ->select($columns)
            ->with(self::RELATIONS)
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->whereLike('title', "%{$search}%")
                ->orWhereLike('primary_keyword', "%{$search}%")))
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($writer, fn ($query) => $query->where('assigned_writer_id', (int) $writer))
            ->when($filters['pillar'] ?? null, fn ($query, $pillar) => $query->where('content_pillar_id', (int) $pillar))
            ->latest('updated_at');

        return Pagination::paginate($query, $filters);
    }

    public function findForClient(Client $client, int $id): Article
    {
        return $client->articles()
            ->with([...self::RELATIONS, 'calendarEntry:id,article_id,title,publish_date'])
            ->findOrFail($id)
            ->setRelation('client', $client);
    }

    public function create(Client $client, User $user, array $data, string $source = 'portal'): Article
    {
        $attributes = $this->prepare($client, $data);
        $entryId = $this->calendarEntryId($client, $data['calendar_entry_id'] ?? null);

        $article = DB::transaction(function () use ($client, $attributes, $entryId): Article {
            $article = $client->articles()->create([
                ...$attributes,
                'workspace_id' => $client->workspace_id,
                'status' => $attributes['status'] ?? ArticleStatus::Draft,
            ]);

            if ($entryId) {
                $client->calendarEntries()->whereKey($entryId)->update(['article_id' => $article->id]);
            }

            return $article->setRelation('client', $client);
        });

        $this->log(ActivityEvent::ArticleCreated, $article, $user, $source, array_filter(['calendar_entry_id' => $entryId]));

        return $article->load([...self::RELATIONS, 'calendarEntry:id,article_id,title,publish_date']);
    }

    public function update(Article $article, User $user, array $data, string $source = 'portal'): Article
    {
        $article->fill($this->prepare($article->client, $data, $article));
        $changed = array_keys($article->getDirty());
        $article->save();

        if ($changed !== []) {
            // Field names only: article text is not copied into the audit trail.
            $this->log(ActivityEvent::ArticleUpdated, $article, $user, $source, ['fields' => $changed]);
        }

        return $this->findForClient($article->client, $article->id);
    }

    /**
     * Score history goes with the article; a linked calendar entry stays and
     * simply loses its link (see the foreign key).
     */
    public function delete(Article $article, User $user, string $source = 'portal'): void
    {
        DB::transaction(function () use ($article, $user, $source): void {
            $this->log(ActivityEvent::ArticleDeleted, $article, $user, $source);

            $article->delete();
        });
    }

    public function scores(Article $article): Collection
    {
        return $article->scores()->with('scorer:id,name')->latest()->orderByDesc('id')->get();
    }

    public function findScore(Article $article, int $id): ArticleScore
    {
        return $article->scores()->with('scorer:id,name')->findOrFail($id);
    }

    public function addScore(Article $article, User $user, array $data, string $source = 'portal'): ArticleScore
    {
        $categories = collect(ArticleScoreCategory::cases())
            ->mapWithKeys(fn (ArticleScoreCategory $category) => [$category->value => $data['category_scores'][$category->value] ?? null])
            ->filter(fn ($score) => $score !== null)
            ->map(fn ($score) => (int) $score)
            ->all();

        $score = $article->scores()->create([
            'workspace_id' => $article->workspace_id,
            'scored_by_user_id' => $user->id,
            'score_type' => $data['score_type'],
            'overall_score' => (int) $data['overall_score'],
            'category_scores' => $categories === [] ? null : $categories,
            'feedback' => filled($data['feedback'] ?? null) ? trim($data['feedback']) : null,
        ]);

        $this->log(ActivityEvent::ArticleScored, $article, $user, $source, [
            'score_type' => $score->score_type->value,
            'overall_score' => $score->overall_score,
        ]);

        return $score->load('scorer:id,name');
    }

    private function prepare(Client $client, array $data, ?Article $article = null): array
    {
        $attributes = Arr::only($data, self::EDITABLE_FIELDS);

        if (array_key_exists('content_pillar_id', $attributes)) {
            $attributes['content_pillar_id'] = $this->pillarId($client, $attributes['content_pillar_id']);
        }

        if (array_key_exists('assigned_writer_id', $attributes)) {
            $attributes['assigned_writer_id'] = $this->writerId($client, $attributes['assigned_writer_id']);
        }

        if (array_key_exists('slug', $attributes)) {
            $attributes['slug'] = filled($attributes['slug']) ? (Str::slug($attributes['slug']) ?: null) : null;
        }

        foreach (['secondary_keywords', 'tags'] as $list) {
            if (array_key_exists($list, $attributes)) {
                $attributes[$list] = KeywordList::clean($attributes[$list]);
            }
        }

        // With a body the count is always derived from it; without one a manual count is kept.
        if (filled($attributes['content_body'] ?? null)) {
            $attributes['word_count'] = count(preg_split('/\s+/u', trim(strip_tags($attributes['content_body'])), -1, PREG_SPLIT_NO_EMPTY));
        }

        // Publishing without a date records now as the published date.
        $status = $attributes['status'] ?? $article?->status?->value;
        $publishedAt = array_key_exists('published_at', $attributes) ? $attributes['published_at'] : $article?->published_at;

        if ($status === ArticleStatus::Published->value && blank($publishedAt)) {
            $attributes['published_at'] = now();
        }

        return $attributes;
    }

    private function calendarEntryId(Client $client, mixed $id): ?int
    {
        if (blank($id)) {
            return null;
        }

        $entry = $client->calendarEntries()->find($id);

        if (! $entry || $entry->article_id) {
            throw ValidationException::withMessages([
                'calendar_entry_id' => $entry ? 'That calendar entry already has an article.' : 'Choose a calendar entry of this client.',
            ]);
        }

        return $entry->id;
    }

    private function log(ActivityEvent $event, Article $article, User $user, string $source, array $details = []): void
    {
        $this->activity->log($event, $article->client->workspace, $user, $article, ['title' => $article->title, ...$details], $source);
    }
}
