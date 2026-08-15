<?php

declare(strict_types=1);

namespace App\Enums\Tenant\Commerce;

/**
 * Invoice lifecycle status.
 */
enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Issued = 'issued';
    case Void = 'void';
}
