<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Models\Tenant\Invoice;
use App\Models\Tenant\User;

/**
 * Authorization for invoices.
 */
class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('invoices.view');
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $user->can('invoices.view');
    }

    public function generate(User $user): bool
    {
        return $user->can('invoices.generate');
    }

    public function download(User $user, Invoice $invoice): bool
    {
        return $user->can('invoices.download') || $user->can('invoices.view');
    }
}
