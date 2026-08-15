<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Tenant\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when an order is cancelled.
 */
class OrderCancelled
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Order $order,
    ) {}
}
