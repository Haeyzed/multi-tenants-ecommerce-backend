<?php

declare(strict_types=1);

namespace App\Enums\Tenant\HR;

/**
 * Employment lifecycle status for a tenant employee profile.
 */
enum EmploymentStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Terminated = 'terminated';
    case OnLeave = 'on_leave';
}
