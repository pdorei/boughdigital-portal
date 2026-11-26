<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum UpworkTrack: string
{
    use HasOptions;

    case Content = 'content';
    case WordPress = 'wordpress';

    public function label(): string
    {
        return match ($this) {
            self::Content => 'Content',
            self::WordPress => 'WordPress',
        };
    }
}
