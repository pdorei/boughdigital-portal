<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Stored in documents.provider. A Google Doc is only a stored link; nothing
 * here talks to the Google APIs.
 */
enum DocumentSource: string
{
    use HasOptions;

    case Upload = 'upload';
    case GoogleDoc = 'google_drive';
    case ExternalLink = 'external_link';

    public function label(): string
    {
        return match ($this) {
            self::Upload => 'Uploaded file',
            self::GoogleDoc => 'Google Doc link',
            self::ExternalLink => 'External link',
        };
    }
}
