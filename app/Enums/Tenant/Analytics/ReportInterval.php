<?php

declare(strict_types=1);

namespace App\Enums\Tenant\Analytics;

/**
 * Bucket size used when grouping time-series report rows.
 */
enum ReportInterval: string
{
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';
}
