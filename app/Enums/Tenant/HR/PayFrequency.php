<?php

declare(strict_types=1);

namespace App\Enums\Tenant\HR;

/**
 * Employee compensation pay frequency.
 */
enum PayFrequency: string
{
    case Monthly = 'monthly';
    case Biweekly = 'biweekly';
    case Weekly = 'weekly';
}
