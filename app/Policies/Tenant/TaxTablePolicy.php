<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Models\Tenant\HR\TaxTable;
use App\Models\Tenant\User;

/**
 * Authorization for PAYE tax tables.
 */
class TaxTablePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('hr.view')
            || $user->can('hr.payroll.view')
            || $user->can('hr.settings.view');
    }

    public function view(User $user, TaxTable $table): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('hr.settings.update') || $user->can('hr.payroll.manage');
    }

    public function update(User $user, TaxTable $table): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, TaxTable $table): bool
    {
        return $this->create($user);
    }
}
