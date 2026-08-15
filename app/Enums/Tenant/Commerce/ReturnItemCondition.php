<?php

declare(strict_types=1);

namespace App\Enums\Tenant\Commerce;

/**
 * Physical condition of a returned item.
 */
enum ReturnItemCondition: string
{
    case New = 'new';
    case Opened = 'opened';
    case Used = 'used';
    case Damaged = 'damaged';
    case Defective = 'defective';
}
