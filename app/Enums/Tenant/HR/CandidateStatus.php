<?php

declare(strict_types=1);

namespace App\Enums\Tenant\HR;

/**
 * Standalone candidate CRM status. Distinct from application pipeline status.
 */
enum CandidateStatus: string
{
    case Active = 'active';
    case Hired = 'hired';
    case Archived = 'archived';
}
