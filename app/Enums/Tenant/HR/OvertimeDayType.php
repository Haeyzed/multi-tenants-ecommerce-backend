<?php

declare(strict_types=1);

namespace App\Enums\Tenant\HR;

/**
 * Overtime day classification used by the policy engine.
 */
enum OvertimeDayType: string
{
    case Weekday = 'weekday';
    case Weekend = 'weekend';
    case Holiday = 'holiday';
}
