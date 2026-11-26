<?php

namespace App\Services\Upwork\Client;

/**
 * The only way the application reads from Upwork. Implementations must use
 * Upwork's official API (GraphqlUpworkClient) or local fixtures
 * (FakeUpworkClient): no scraping, browser automation or session cookies.
 * There is deliberately no method that submits a proposal.
 */
interface UpworkClient
{
    /**
     * Searches newest-first. One call to this method is one API request.
     *
     * @return list<UpworkJobData>
     *
     * @throws UpworkRateLimitedException when Upwork reports a rate limit; callers stop the run
     * @throws UpworkAuthenticationException when the connection is missing or rejected
     * @throws UpworkApiException for any other API failure
     */
    public function searchJobs(UpworkSearchQuery $query): array;
}
