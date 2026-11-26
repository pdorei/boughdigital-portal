<?php

namespace App\Services\Upwork;

final readonly class HardRuleResult
{
    /**
     * @param  list<string>  $reviewReasons
     */
    public function __construct(
        public ?string $skipReason = null,
        public array $reviewReasons = [],
    ) {}

    public function skips(): bool
    {
        return $this->skipReason !== null;
    }

    public function needsReview(): bool
    {
        return $this->reviewReasons !== [];
    }
}
