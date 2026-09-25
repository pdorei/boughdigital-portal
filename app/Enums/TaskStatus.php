<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum TaskStatus: string
{
    use HasOptions;

    case Backlog = 'backlog';
    case NextUp = 'next_up';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case OnHold = 'on_hold';

    public function label(): string
    {
        return match ($this) {
            self::Backlog => 'Backlog',
            self::NextUp => 'Next Up',
            self::InProgress => 'In Progress',
            self::Completed => 'Completed',
            self::OnHold => 'On Hold',
        };
    }
}
