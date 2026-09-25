<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum DocumentFolder: string
{
    use HasOptions;

    case Seo = 'seo';
    case Strategy = 'strategy';
    case Content = 'content';
    case Reports = 'reports';
    case Other = 'other';

    public function label(): string
    {
        return $this === self::Seo ? 'SEO' : ucfirst($this->value);
    }
}
