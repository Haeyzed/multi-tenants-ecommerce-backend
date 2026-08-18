<?php

declare(strict_types=1);

namespace App\Enums\Tenant\HR;

/**
 * Why an employment history snapshot was recorded.
 */
enum EmploymentChangeType: string
{
    case Hired = 'hired';
    case AssignmentChanged = 'assignment_changed';
    case StatusChanged = 'status_changed';
}
