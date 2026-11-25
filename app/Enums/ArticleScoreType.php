<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum ArticleScoreType: string
{
    use HasOptions;

    case Ai = 'ai';
    case Human = 'human';

    public function label(): string
    {
        return match ($this) {
            self::Ai => 'AI',
            self::Human => 'Human',
        };
    }
}
