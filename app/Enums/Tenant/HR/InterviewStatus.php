<?php

declare(strict_types=1);

namespace App\Enums\Tenant\HR;

/**
 * Interview scheduling lifecycle.
 */
enum InterviewStatus: string
{
    case Scheduled = 'scheduled';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Rescheduled = 'rescheduled';
    case NoShow = 'no_show';
}
