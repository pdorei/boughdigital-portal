<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum UpworkRunStatus: string
{
    use HasOptions;

    case Running = 'running';
    case Completed = 'completed';
    case RateLimited = 'rate_limited';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::RateLimited => 'Rate limited',
            default => ucfirst($this->value),
        };
    }
}
