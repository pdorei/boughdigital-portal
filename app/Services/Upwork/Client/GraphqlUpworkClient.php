<?php

namespace App\Services\Upwork\Client;

use App\Enums\UpworkJobType;
use App\Services\Upwork\UpworkOAuthService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Upwork's official GraphQL API (marketplaceJobPostingsSearch), authenticated
 * with the connected account's OAuth2 token. Read-only: it never submits
 * anything. A rate-limit response is surfaced immediately and never retried.
 *
 * Notes from the published schema: pagination_eq currently makes the
 * resolver fail, so only the default first page (newest first) is read; the
 * frequent schedule plus de-duplication covers the rest.
 */
class GraphqlUpworkClient implements UpworkClient
{
    public const SEARCH_QUERY = <<<'GRAPHQL'
query searchJobs(
  $marketPlaceJobFilter: MarketplaceJobPostingsSearchFilter,
  $searchType: MarketplaceJobPostingSearchType,
  $sortAttributes: [MarketplaceJobPostingSearchSortAttribute]
) {
  marketplaceJobPostingsSearch(
    marketPlaceJobFilter: $marketPlaceJobFilter
    searchType: $searchType
    sortAttributes: $sortAttributes
  ) {
    edges {
      node {
        id
        ciphertext
        title
        description
        publishedDateTime
        experienceLevel
        duration
        amount { rawValue currency }
        hourlyBudgetMin { rawValue currency }
        hourlyBudgetMax { rawValue currency }
        skills { name prettyName }
        totalApplicants
        client {
          totalHires
          totalPostedJobs
          totalReviews
          totalFeedback
          verificationStatus
          totalSpent { rawValue currency }
          location { country }
        }
      }
    }
  }
}
GRAPHQL;

    public function __construct(private UpworkOAuthService $oauth) {}

    public function searchJobs(UpworkSearchQuery $query): array
    {
        $filter = ['searchExpression_eq' => $query->keyword];

        if ($query->jobType) {
            $filter['jobType_eq'] = $query->jobType === UpworkJobType::Hourly ? 'HOURLY' : 'FIXED';
        }

        $data = $this->post(self::SEARCH_QUERY, [
            'marketPlaceJobFilter' => $filter,
            'searchType' => 'USER_JOBS_SEARCH',
            'sortAttributes' => [['field' => 'RECENCY']],
        ]);

        return collect(data_get($data, 'marketplaceJobPostingsSearch.edges', []))
            ->pluck('node')
            ->filter()
            ->map(fn (array $node) => UpworkJobData::fromNode($node))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>
     */
    public function post(string $query, array $variables = []): array
    {
        $token = $this->oauth->accessToken();

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout((int) config('upwork.timeout', 30))
                ->post(config('upwork.graphql_url'), ['query' => $query, 'variables' => $variables]);
        } catch (ConnectionException) {
            throw new UpworkApiException('Could not reach the Upwork API.');
        }

        if ($response->status() === 429) {
            throw new UpworkRateLimitedException('Upwork rate limit reached (HTTP 429).');
        }

        if (in_array($response->status(), [401, 403], true)) {
            throw new UpworkAuthenticationException("Upwork rejected the connection (HTTP {$response->status()}). Reconnect Upwork.");
        }

        if ($response->failed()) {
            throw new UpworkApiException("Upwork API error (HTTP {$response->status()}).");
        }

        $errors = $response->json('errors') ?? [];

        if ($errors !== []) {
            $message = (string) ($errors[0]['message'] ?? 'Unknown GraphQL error');
            Log::warning('upwork.graphql_error', ['message' => Str::limit($message, 300)]);

            if (Str::contains(Str::lower($message), ['rate limit', 'too many requests', 'throttl'])) {
                throw new UpworkRateLimitedException('Upwork rate limit reached.');
            }

            if (Str::contains(Str::lower($message), ['permission', 'scope', 'unauthor', 'token'])) {
                throw new UpworkAuthenticationException('Upwork denied access: '.Str::limit($message, 200));
            }

            throw new UpworkApiException('Upwork API error: '.Str::limit($message, 200));
        }

        return (array) $response->json('data', []);
    }
}
