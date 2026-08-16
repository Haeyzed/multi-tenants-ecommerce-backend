<?php

declare(strict_types=1);

namespace App\Enums\Tenant\Driver;

/**
 * Driver availability for delivery assignment.
 */
enum DriverAvailability: string
{
    case Available = 'available';
    case Unavailable = 'unavailable';
    case OnDelivery = 'on_delivery';
}
