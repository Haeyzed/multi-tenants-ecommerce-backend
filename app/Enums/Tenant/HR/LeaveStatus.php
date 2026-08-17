<?php

declare(strict_types=1);

namespace App\Enums\Tenant\HR;

/**
 * Leave request review lifecycle.
 */
enum LeaveStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
}
