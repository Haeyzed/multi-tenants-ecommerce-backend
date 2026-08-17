<?php

declare(strict_types=1);

namespace App\Enums\Tenant\HR;

/**
 * Leave request category.
 */
enum LeaveType: string
{
    case Annual = 'annual';
    case Sick = 'sick';
    case Unpaid = 'unpaid';
    case Other = 'other';
}
