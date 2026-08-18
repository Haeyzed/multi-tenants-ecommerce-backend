<?php

declare(strict_types=1);

namespace App\Enums\Tenant\HR;

/**
 * Lifecycle of an interview meeting created by a provider.
 */
enum InterviewMeetingStatus: string
{
    case Pending = 'pending';
    case Created = 'created';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Superseded = 'superseded';
}
