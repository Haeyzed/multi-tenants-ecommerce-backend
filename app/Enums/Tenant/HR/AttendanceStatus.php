<?php

declare(strict_types=1);

namespace App\Enums\Tenant\HR;

/**
 * Daily attendance status for an employee.
 */
enum AttendanceStatus: string
{
    case Present = 'present';
    case Absent = 'absent';
    case Late = 'late';
    case OnLeave = 'on_leave';
    case Holiday = 'holiday';
}
