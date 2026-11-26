<?php

namespace App\Services\Upwork\Client;

use App\Enums\UpworkJobType;
use App\Enums\UpworkSearch;
use App\Enums\UpworkTrack;

/**
 * One keyword searched one way (A, B or C). Only the keyword and job type are
 * sent to Upwork; the other filters are applied in code by the discovery
 * service, because the API filter types for budget and location are not
 * confirmed and experience level accepts a single value.
 */
final readonly class UpworkSearchQuery
{
    /**
     * @param  array<string, mixed>  $filters  the search's settings (budget, rate, countries, levels)
     */
    public function __construct(
        public string $keyword,
        public UpworkTrack $track,
        public UpworkSearch $search,
        public ?UpworkJobType $jobType,
        public array $filters = [],
    ) {}
}
