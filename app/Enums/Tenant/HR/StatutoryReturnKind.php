<?php

declare(strict_types=1);

namespace App\Enums\Tenant\HR;

/**
 * Statutory filing schedule type.
 */
enum StatutoryReturnKind: string
{
    case Paye = 'paye';
    case Pension = 'pension';
    case Nhf = 'nhf';
    case Nsitf = 'nsitf';
    case Combined = 'combined';
}
