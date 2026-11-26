<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum UpworkJobStatus: string
{
    use HasOptions;

    case New = 'new';
    case Skipped = 'skipped';
    case Review = 'review';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case ProposalSent = 'proposal_sent';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Skipped => 'Skipped',
            self::Review => 'Awaiting review',
            self::Accepted => 'Accepted',
            self::Rejected => 'Rejected',
            self::ProposalSent => 'Proposal sent',
            self::Expired => 'Expired',
        };
    }
}
