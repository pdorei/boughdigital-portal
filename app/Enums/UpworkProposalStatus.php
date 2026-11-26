<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum UpworkProposalStatus: string
{
    use HasOptions;

    case Pending = 'pending';
    case Approved = 'approved';
    case Sent = 'sent';
    case Rejected = 'rejected';
    case Expired = 'expired';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * Statuses that still need a decision or a send.
     */
    public function isOpen(): bool
    {
        return in_array($this, [self::Pending, self::Approved], true);
    }
}
