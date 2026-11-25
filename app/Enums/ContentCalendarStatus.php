<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum ContentCalendarStatus: string
{
    use HasOptions;

    case Planned = 'planned';
    case Assigned = 'assigned';
    case Writing = 'writing';
    case Review = 'review';
    case Approved = 'approved';
    case Scheduled = 'scheduled';
    case Published = 'published';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Planned => 'Planned',
            self::Assigned => 'Assigned',
            self::Writing => 'Writing',
            self::Review => 'Review',
            self::Approved => 'Approved',
            self::Scheduled => 'Scheduled',
            self::Published => 'Published',
            self::Cancelled => 'Cancelled',
        };
    }
}
