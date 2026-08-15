<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Tenant\Shipment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a shipment is marked delivered.
 */
class ShipmentDelivered
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Shipment $shipment,
    ) {}
}
