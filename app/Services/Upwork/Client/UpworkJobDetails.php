<?php

namespace App\Services\Upwork\Client;

/**
 * What a job-details call adds to a search result. Every field is nullable:
 * null means "not returned", and never overwrites a known value.
 */
final readonly class UpworkJobDetails
{
    /**
     * @param  list<string>|null  $screeningQuestions
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public ?array $screeningQuestions = null,
        public ?bool $clientPaymentVerified = null,
        public ?string $clientCountry = null,
        public ?float $clientTotalSpent = null,
        public ?float $clientHireRate = null,
        public ?float $clientRating = null,
        public ?int $clientReviewsCount = null,
        public ?int $proposalsCount = null,
        public array $raw = [],
    ) {}

    /**
     * Attributes to merge into the stored job (nulls left out).
     *
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return array_filter([
            'screening_questions' => $this->screeningQuestions,
            'client_payment_verified' => $this->clientPaymentVerified,
            'client_country' => $this->clientCountry,
            'client_total_spent' => $this->clientTotalSpent,
            'client_hire_rate' => $this->clientHireRate,
            'client_rating' => $this->clientRating,
            'client_reviews_count' => $this->clientReviewsCount,
            'proposals_count' => $this->proposalsCount,
        ], fn ($value) => $value !== null);
    }
}
