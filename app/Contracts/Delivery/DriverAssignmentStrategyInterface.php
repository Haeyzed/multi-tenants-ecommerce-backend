<?php

declare(strict_types=1);

namespace App\Contracts\Delivery;

use App\Models\Tenant\Delivery;
use App\Models\Tenant\Driver;

/**
 * Selects a driver for a delivery (or declines automatic selection).
 */
interface DriverAssignmentStrategyInterface
{
    /**
     * Return a driver to assign, or null when no automatic pick is available.
     */
    public function assign(Delivery $delivery): ?Driver;
}
