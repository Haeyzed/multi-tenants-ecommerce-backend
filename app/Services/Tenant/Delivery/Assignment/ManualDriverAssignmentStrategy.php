<?php

declare(strict_types=1);

namespace App\Services\Tenant\Delivery\Assignment;

use App\Contracts\Delivery\DriverAssignmentStrategyInterface;
use App\Models\Tenant\Delivery;
use App\Models\Tenant\Driver;

/**
 * Manual assignment — never auto-picks a driver.
 */
class ManualDriverAssignmentStrategy implements DriverAssignmentStrategyInterface
{
    public function assign(Delivery $delivery): ?Driver
    {
        return null;
    }
}
