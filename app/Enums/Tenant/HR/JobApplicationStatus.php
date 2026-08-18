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
    case Interview = 'interview';
    case Offered = 'offered';
    case Hired = 'hired';
    case Rejected = 'rejected';
}
