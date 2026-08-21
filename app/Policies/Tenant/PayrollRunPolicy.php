<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Models\HR\PayrollRun;
use App\Models\Tenant\User;

/**
 * Authorization for payroll runs.
 */
class PayrollRunPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('hr.payroll.view')
            || $user->can('hr.payroll.manage')
            || $user->can('hr.view');
    }

    public function view(User $user, PayrollRun $payrollRun): bool
    {
        return $user->can('hr.payroll.view')
            || $user->can('hr.payroll.manage')
            || $user->can('hr.view');
    }

    public function create(User $user): bool
    {
        return $user->can('hr.payroll.manage');
    }

    public function manage(User $user, ?PayrollRun $payrollRun = null): bool
    {
        return $user->can('hr.payroll.manage');
    }

    public function pay(User $user, PayrollRun $payrollRun): bool
    {
        return $user->can('hr.payroll.manage');
    }

    public function approve(User $user, PayrollRun $payrollRun): bool
    {
        return $user->can('hr.payroll.approve') || $user->can('hr.payroll.manage');
    }
}
