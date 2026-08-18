<?php

declare(strict_types=1);

namespace App\Enums\Tenant\HR;

/**
 * Candidate application pipeline status.
 */
enum JobApplicationStatus: string
{
    case Received = 'received';
    case Screening = 'screening';
    case Shortlisted = 'shortlisted';
    case Interview = 'interview';
    case Offered = 'offered';
    case Hired = 'hired';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Hired, self::Rejected, self::Withdrawn], true);
    }
}
