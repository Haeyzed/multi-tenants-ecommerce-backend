<?php

declare(strict_types=1);

namespace App\Enums\Tenant\HR;

/**
 * Individual performance review status.
 */
enum PerformanceReviewStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
}
