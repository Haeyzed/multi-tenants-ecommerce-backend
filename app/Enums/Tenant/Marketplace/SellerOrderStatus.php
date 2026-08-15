<?php

declare(strict_types=1);

namespace App\Enums\Tenant\Marketplace;

/**
 * Fulfillment lifecycle for a seller sub-order.
 */
enum SellerOrderStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Processing = 'processing';
    case ReadyToShip = 'ready_to_ship';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
}
