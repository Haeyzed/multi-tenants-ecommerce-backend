<?php

declare(strict_types=1);

namespace App\Enums\Tenant\Delivery;

/**
 * Delivery lifecycle status.
 */
enum DeliveryStatus: string
{
    case Pending = 'pending';
    case Assigned = 'assigned';
    case Accepted = 'accepted';
    case PickedUp = 'picked_up';
    case OutForDelivery = 'out_for_delivery';
    case Delivered = 'delivered';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
