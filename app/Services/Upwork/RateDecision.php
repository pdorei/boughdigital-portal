<?php

namespace App\Services\Upwork;

use App\Enums\UpworkJobType;

final readonly class RateDecision
{
    public const BID = 'bid';

    public const SKIP = 'skip';

    public const REVIEW = 'review';

    public function __construct(
        public string $action,
        public UpworkJobType $bidType,
        public ?float $amount,
        public string $reason,
    ) {}

    public function isBid(): bool
    {
        return $this->action === self::BID;
    }
}
