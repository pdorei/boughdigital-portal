<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum UpworkDecisionOutcome: string
{
    use HasOptions;

    case Skip = 'skip';
    case Review = 'review';
    case Accept = 'accept';
    case Reject = 'reject';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function jobStatus(): UpworkJobStatus
    {
        return match ($this) {
            self::Skip => UpworkJobStatus::Skipped,
            self::Review => UpworkJobStatus::Review,
            self::Accept => UpworkJobStatus::Accepted,
            self::Reject => UpworkJobStatus::Rejected,
        };
    }
}
