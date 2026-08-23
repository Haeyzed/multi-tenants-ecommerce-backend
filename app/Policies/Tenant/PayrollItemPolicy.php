<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Models\Tenant\HR\PayrollItem;
use App\Models\Tenant\User;

/**
 * Authorization for individual payslips.
 */
class PayrollItemPolicy
{
    public function view(User $user, PayrollItem $payrollItem): bool
    {
        if ($user->can('hr.payroll.view') || $user->can('hr.payroll.manage') || $user->can('hr.view')) {
            return true;
        }

        return $payrollItem->employee?->user_id === $user->id;
    }
}
