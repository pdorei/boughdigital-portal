<?php

namespace App\Services\Upwork\Client;

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
    /*
     * Field names follow Upwork's published GraphQL schema. Fields marked
     * UNVERIFIED have not yet been checked against a live response (no API key
     * at the time of writing): they appear in a community mirror of the schema
     * (appspringtechsas/upwork-api-markdown, October 2026) but not in a call we
     * have made. Every one is parsed null-safely, and a null payment status
     * sends the job to review, never to skip.
     *
     * TODO(upwork-api): once the key is approved, run one search and one
     * details call, confirm the UNVERIFIED fields return data, and remove the
     * markers (or the fields) accordingly.
     */
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
          totalReviews
          totalSpent { rawValue currency }
          location { country }
          # UNVERIFIED: verificationStatus, totalPostedJobs, totalFeedback (see TODO above)
          verificationStatus
          totalPostedJobs
          totalFeedback
        }
      }
    }
  }
}
GRAPHQL;

    /**
     * UNVERIFIED as a whole (see TODO above): marketplaceJobPosting(id),
     * contractorSelection.proposalRequirement.screeningQuestions and
     * clientCompanyPublic.paymentVerification come from the community schema
     * mirror. It is also unconfirmed whether "id" takes the search result's
     * id or its ciphertext; the search result's id is sent.
     */
    public const DETAILS_QUERY = <<<'GRAPHQL'
query jobDetails($id: ID!) {
  marketplaceJobPosting(id: $id) {
    id
    contractorSelection {
      proposalRequirement {
        screeningQuestions { question sequenceNumber }
      }
    }
    clientCompanyPublic {
      paymentVerification { status paymentVerified }
    }
  }
}
GRAPHQL;

    public function __construct(private UpworkOAuthService $oauth) {}

    public function searchJobs(UpworkSearchQuery $query): array
    {
        // Only the keyword is sent: searches A/B/C are applied in code.
        $data = $this->post(self::SEARCH_QUERY, [
            'marketPlaceJobFilter' => ['searchExpression_eq' => $query->keyword],
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

    public function jobDetails(string $postingId): ?UpworkJobDetails
    {
        $posting = data_get($this->post(self::DETAILS_QUERY, ['id' => $postingId]), 'marketplaceJobPosting');

        if (! is_array($posting)) {
            return null;
        }

        $questions = collect(data_get($posting, 'contractorSelection.proposalRequirement.screeningQuestions') ?? [])
            ->filter(fn ($item) => is_array($item) && filled($item['question'] ?? null))
            ->sortBy(fn (array $item) => $item['sequenceNumber'] ?? PHP_INT_MAX)
            ->pluck('question')
            ->map(fn ($question) => trim((string) $question))
            ->values()
            ->all();

        $verification = data_get($posting, 'clientCompanyPublic.paymentVerification');
        $verified = is_array($verification)
            ? (is_bool($verification['paymentVerified'] ?? null) ? $verification['paymentVerified'] : UpworkJobData::paymentVerified($verification['status'] ?? null))
            : null;

        return new UpworkJobDetails(
            screeningQuestions: $questions ?: null,
            clientPaymentVerified: $verified,
            raw: $posting,
        );
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
