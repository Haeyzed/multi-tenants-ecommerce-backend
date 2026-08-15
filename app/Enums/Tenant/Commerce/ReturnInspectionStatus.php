<?php

declare(strict_types=1);

namespace App\Enums\Tenant\Commerce;

/**
 * Inspection outcome for a return line.
 */
enum ReturnInspectionStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
}
