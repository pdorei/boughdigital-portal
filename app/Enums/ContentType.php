<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum ContentType: string
{
    use HasOptions;

    case Article = 'article';
    case Blog = 'blog';
    case LandingPage = 'landing_page';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Article => 'Article',
            self::Blog => 'Blog',
            self::LandingPage => 'Landing page',
            self::Other => 'Other',
        };
    }
}
