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
    case Closed = 'closed';
}
