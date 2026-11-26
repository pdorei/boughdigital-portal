<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * The three ways every keyword is searched each run. Their filter values are
 * editable in the Upwork settings; these are the identities and defaults.
 */
enum UpworkSearch: string
{
    use HasOptions;

    case A = 'a';
    case B = 'b';
    case C = 'c';

    public function label(): string
    {
        return match ($this) {
            self::A => 'A: Fixed price',
            self::B => 'B: Hourly',
            self::C => 'C: Australian clients',
        };
    }
}
