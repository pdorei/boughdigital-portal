<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum ArticleScoreCategory: string
{
    use HasOptions;

    case Seo = 'seo';
    case Readability = 'readability';
    case BrandVoice = 'brand_voice';
    case Structure = 'structure';
    case Originality = 'originality';

    public function label(): string
    {
        return match ($this) {
            self::Seo => 'SEO',
            self::Readability => 'Readability',
            self::BrandVoice => 'Brand voice',
            self::Structure => 'Structure',
            self::Originality => 'Originality',
        };
    }
}
