<?php

declare(strict_types=1);

namespace App\Enums\Tenant\HR;

/**
 * Recruitment job opening lifecycle.
 */
enum JobOpeningStatus: string
{
    case Draft = 'draft';
    case Open = 'open';
    case Paused = 'paused';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    public function isPubliclyListable(): bool
    {
        return $this === self::Open;
    }

    public function acceptsApplications(): bool
    {
        return $this === self::Open;
    }
}
