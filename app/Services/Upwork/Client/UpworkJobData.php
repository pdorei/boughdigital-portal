<?php

namespace App\Services\Upwork\Client;

use App\Enums\UpworkJobType;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;

/**
 * A job as returned by marketplaceJobPostingsSearch (edges[].node), normalised.
 */
final readonly class UpworkJobData
{
    /**
     * @param  list<string>  $skills
     * @param  list<string>|null  $screeningQuestions
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $upworkJobId,
        public string $title,
        public ?string $description,
        public string $url,
        public UpworkJobType $jobType,
        public ?float $hourlyMin,
        public ?float $hourlyMax,
        public ?float $fixedBudget,
        public ?string $experienceLevel,
        public ?string $duration,
        public array $skills,
        public ?int $proposalsCount,
        public ?string $clientCountry,
        public ?bool $clientPaymentVerified,
        public ?float $clientTotalSpent,
        public ?float $clientHireRate,
        public ?float $clientRating,
        public ?int $clientReviewsCount,
        public ?array $screeningQuestions,
        public ?CarbonImmutable $postedAt,
        public array $raw,
    ) {}

    /**
     * @param  array<string, mixed>  $node
     */
    public static function fromNode(array $node): self
    {
        $hourlyMin = self::money($node['hourlyBudgetMin'] ?? null);
        $hourlyMax = self::money($node['hourlyBudgetMax'] ?? null);
        $amount = self::money($node['amount'] ?? null);
        $jobType = ($hourlyMin || $hourlyMax) || strtoupper((string) ($node['jobType'] ?? '')) === 'HOURLY'
            ? UpworkJobType::Hourly
            : UpworkJobType::Fixed;
        $client = $node['client'] ?? [];
        $hires = $client['totalHires'] ?? null;
        $posted = $client['totalPostedJobs'] ?? null;
        $ciphertext = (string) ($node['ciphertext'] ?? $node['id']);

        return new self(
            upworkJobId: $ciphertext,
            title: trim((string) ($node['title'] ?? 'Untitled job')),
            description: $node['description'] ?? null,
            url: rtrim((string) config('upwork.job_url'), '/').'/'.$ciphertext,
            jobType: $jobType,
            hourlyMin: $hourlyMin,
            hourlyMax: $hourlyMax,
            // amount is non-null in the schema but means nothing for hourly jobs.
            fixedBudget: $jobType === UpworkJobType::Fixed && $amount > 0 ? $amount : null,
            experienceLevel: isset($node['experienceLevel']) ? strtolower((string) $node['experienceLevel']) : null,
            duration: isset($node['duration']) ? strtolower((string) $node['duration']) : null,
            skills: array_values(array_filter(Arr::map($node['skills'] ?? [], fn ($skill) => is_array($skill) ? ($skill['prettyName'] ?? $skill['name'] ?? null) : $skill))),
            proposalsCount: isset($node['totalApplicants']) ? (int) $node['totalApplicants'] : null,
            clientCountry: data_get($client, 'location.country'),
            clientPaymentVerified: self::paymentVerified($client['verificationStatus'] ?? null),
            clientTotalSpent: self::money($client['totalSpent'] ?? null),
            clientHireRate: $hires !== null && $posted ? round(min(100, $hires / $posted * 100), 2) : null,
            clientRating: isset($client['totalFeedback']) ? (float) $client['totalFeedback'] : null,
            clientReviewsCount: isset($client['totalReviews']) ? (int) $client['totalReviews'] : null,
            // Search results carry no screening questions; the job-details call adds them.
            screeningQuestions: null,
            postedAt: isset($node['publishedDateTime']) ? CarbonImmutable::parse($node['publishedDateTime']) : null,
            raw: $node,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'upwork_job_id' => $this->upworkJobId,
            'title' => mb_substr($this->title, 0, 255),
            'description' => $this->description,
            'url' => $this->url,
            'job_type' => $this->jobType,
            'hourly_min' => $this->hourlyMin,
            'hourly_max' => $this->hourlyMax,
            'fixed_budget' => $this->fixedBudget,
            'experience_level' => $this->experienceLevel,
            'duration' => $this->duration,
            'skills' => $this->skills,
            'proposals_count' => $this->proposalsCount,
            'client_country' => $this->clientCountry,
            'client_payment_verified' => $this->clientPaymentVerified,
            'client_total_spent' => $this->clientTotalSpent,
            'client_hire_rate' => $this->clientHireRate,
            'client_rating' => $this->clientRating,
            'client_reviews_count' => $this->clientReviewsCount,
            'screening_questions' => $this->screeningQuestions,
            'raw' => $this->raw,
            'posted_at' => $this->postedAt,
        ];
    }

    /**
     * VERIFIED is true, a definite "not verified" is false, and anything
     * unknown (missing field, UNKNOWN, in progress) is null. The hard rules
     * send null and false to review; neither is ever a reason to skip.
     */
    public static function paymentVerified(mixed $status): ?bool
    {
        if (is_bool($status)) {
            return $status;
        }

        return match (strtoupper((string) $status)) {
            'VERIFIED' => true,
            'NOT_VERIFIED', 'ACTIVE_BUT_NOT_VERIFIED' => false,
            default => null,
        };
    }

    private static function money(mixed $money): ?float
    {
        $value = is_array($money) ? ($money['rawValue'] ?? null) : $money;

        return is_numeric($value) ? (float) $value : null;
    }
}
