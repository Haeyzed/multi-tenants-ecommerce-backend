<?php

declare(strict_types=1);

namespace App\Enums\Tenant\HR;

/**
 * Performance review cycle lifecycle.
 */
enum PerformanceCycleStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Closed = 'closed';
}
