<?php

namespace App\Services\Upwork\Client;

use App\Enums\UpworkTrack;

/**
 * One keyword, searched once per run, newest first. Searches A/B/C are not
 * sent to Upwork: the discovery service applies them in code to this result
 * set, which keeps a run to one search call per keyword.
 */
final readonly class UpworkSearchQuery
{
    public function __construct(
        public string $keyword,
        public UpworkTrack $track,
    ) {}
}
