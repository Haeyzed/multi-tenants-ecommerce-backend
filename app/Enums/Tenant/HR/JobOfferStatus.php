<?php

declare(strict_types=1);

namespace App\Enums\Tenant\HR;

/**
 * Offer lifecycle. Approval inserts PendingApproval when HR settings require it.
 */
enum JobOfferStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case Withdrawn = 'withdrawn';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Accepted, self::Rejected, self::Expired, self::Withdrawn], true);
    }
}
