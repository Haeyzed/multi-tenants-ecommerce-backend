<?php

declare(strict_types=1);

namespace App\Enums\Tenant\HR;

/**
 * Contract classification for an employee profile.
 */
enum EmploymentType: string
{
    case FullTime = 'full_time';
    case PartTime = 'part_time';
    case Contract = 'contract';
    case Temporary = 'temporary';
    case Intern = 'intern';
}
