<?php

declare(strict_types=1);

namespace App\Enums\Tenant\HR;

/**
 * How an attendance clock event was captured.
 */
enum AttendanceClockSource: string
{
    case Manual = 'manual';
    case Web = 'web';
    case Gps = 'gps';
    case Biometric = 'biometric';
}
