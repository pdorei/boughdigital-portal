<?php

namespace App\Services\Upwork\Client;

/**
 * The only way the application reads from Upwork. Implementations must use
 * Upwork's official API (GraphqlUpworkClient) or local fixtures
 * (FakeUpworkClient): no scraping, browser automation or session cookies.
 * There is deliberately no method that submits a proposal.
 *
 * Every method call is exactly one API request, so callers can count calls.
 */
interface UpworkClient
{
    /**
     * Newest jobs for one keyword (one request).
     *
     * @return list<UpworkJobData>
     *
     * @throws UpworkRateLimitedException when Upwork reports a rate limit; callers stop the run
     * @throws UpworkAuthenticationException when the connection is missing or rejected
     * @throws UpworkApiException for any other API failure
     */
    public function searchJobs(UpworkSearchQuery $query): array;

    /**
     * Details for one job posting (one request): screening questions and the
     * client information the search result does not carry. Null when the job
     * is no longer available.
     *
     * @throws UpworkRateLimitedException|UpworkAuthenticationException|UpworkApiException
     */
    public function jobDetails(string $postingId): ?UpworkJobDetails;
}
