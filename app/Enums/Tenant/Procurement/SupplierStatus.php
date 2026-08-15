<?php

declare(strict_types=1);

namespace App\Enums\Tenant\Procurement;

/**
 * Supplier account status.
 */
enum SupplierStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
