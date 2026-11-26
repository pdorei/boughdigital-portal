<?php

namespace App\Services\Upwork\Client;

use App\Enums\UpworkJobType;
use Illuminate\Support\Str;

/**
 * Reads jobs from a JSON fixture (the same node shape the GraphQL API returns)
 * so the pipeline can run before the Upwork API key is approved. Fixtures use
 * "posted_minutes_ago" instead of a fixed publishedDateTime so they stay fresh.
 * No network calls are made.
 */
class FakeUpworkClient implements UpworkClient
{
    public int $calls = 0;

    /** @var list<UpworkSearchQuery> */
    public array $queries = [];

    /**
     * @param  list<array<string, mixed>>|null  $nodes  null loads the fixture file
     * @param  int|null  $rateLimitAfter  throw a rate limit on this call number (testing)
     */
    public function __construct(private ?array $nodes = null, private ?int $rateLimitAfter = null) {}

    public function searchJobs(UpworkSearchQuery $query): array
    {
        $this->calls++;
        $this->queries[] = $query;

        if ($this->rateLimitAfter !== null && $this->calls >= $this->rateLimitAfter) {
            throw new UpworkRateLimitedException('Rate limit reached (fixture).');
        }

        return collect($this->nodes())
            ->filter(fn (array $node) => Str::contains(
                Str::lower(($node['title'] ?? '').' '.($node['description'] ?? '')),
                Str::lower($query->keyword),
            ))
            ->filter(fn (array $node) => match ($query->jobType) {
                UpworkJobType::Hourly => isset($node['hourlyBudgetMin']) || isset($node['hourlyBudgetMax']),
                UpworkJobType::Fixed => ! isset($node['hourlyBudgetMin']) && ! isset($node['hourlyBudgetMax']),
                null => true,
            })
            ->map(fn (array $node) => UpworkJobData::fromNode($this->withPostedTime($node)))
            ->sortByDesc(fn (UpworkJobData $job) => $job->postedAt?->getTimestamp())
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function nodes(): array
    {
        if ($this->nodes !== null) {
            return $this->nodes;
        }

        $path = config('upwork.fixtures_path');

        return is_file($path) ? json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR) : [];
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function withPostedTime(array $node): array
    {
        if (isset($node['posted_minutes_ago'])) {
            $node['publishedDateTime'] = now()->subMinutes((int) $node['posted_minutes_ago'])->toIso8601String();
        }

        return $node;
    }
}
