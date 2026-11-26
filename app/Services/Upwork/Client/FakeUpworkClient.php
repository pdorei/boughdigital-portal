<?php

namespace App\Services\Upwork\Client;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Reads jobs from a JSON fixture (the same node shape the GraphQL API returns)
 * so the pipeline can run before the Upwork API key is approved. Fixtures use
 * "posted_minutes_ago" instead of a fixed publishedDateTime so they stay
 * fresh, and keep job-details data under "details" so search results look
 * like the real ones (no screening questions). No network calls are made.
 */
class FakeUpworkClient implements UpworkClient
{
    /** Total calls of both kinds, the way a run counts them. */
    public int $calls = 0;

    public int $searchCalls = 0;

    /** @var list<string> */
    public array $detailRequests = [];

    /** @var list<UpworkSearchQuery> */
    public array $queries = [];

    /**
     * @param  list<array<string, mixed>>|null  $nodes  null loads the fixture file
     * @param  int|null  $rateLimitAfter  throw a rate limit on this call number, counting both kinds (testing)
     */
    public function __construct(private ?array $nodes = null, private ?int $rateLimitAfter = null) {}

    public function searchJobs(UpworkSearchQuery $query): array
    {
        $this->searchCalls++;
        $this->queries[] = $query;
        $this->countCall();

        return collect($this->nodes())
            ->filter(fn (array $node) => Str::contains(
                Str::lower(($node['title'] ?? '').' '.($node['description'] ?? '')),
                Str::lower($query->keyword),
            ))
            ->map(fn (array $node) => UpworkJobData::fromNode(Arr::except($this->withPostedTime($node), ['details'])))
            ->sortByDesc(fn (UpworkJobData $job) => $job->postedAt?->getTimestamp())
            ->values()
            ->all();
    }

    public function jobDetails(string $postingId): ?UpworkJobDetails
    {
        $this->detailRequests[] = $postingId;
        $this->countCall();

        $node = collect($this->nodes())->first(fn (array $node) => in_array($postingId, [$node['id'] ?? null, $node['ciphertext'] ?? null], true));

        if (! $node) {
            return null;
        }

        $details = $node['details'] ?? [];

        return new UpworkJobDetails(
            screeningQuestions: array_values(array_filter((array) ($details['screeningQuestions'] ?? []))) ?: null,
            clientPaymentVerified: array_key_exists('paymentVerified', $details) ? UpworkJobData::paymentVerified($details['paymentVerified']) : null,
            clientCountry: $details['country'] ?? null,
            raw: $details,
        );
    }

    private function countCall(): void
    {
        $this->calls++;

        if ($this->rateLimitAfter !== null && $this->calls >= $this->rateLimitAfter) {
            throw new UpworkRateLimitedException('Rate limit reached (fixture).');
        }
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
