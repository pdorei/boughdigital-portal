<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum ArticleStatus: string
{
    use HasOptions;

    case Draft = 'draft';
    case Review = 'review';
    case Approved = 'approved';
    case Published = 'published';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Review => 'Review',
            self::Approved => 'Approved',
            self::Published => 'Published',
            self::Archived => 'Archived',
        };
    }
}
