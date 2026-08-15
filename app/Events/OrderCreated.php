<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Tenant\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a customer order is created at checkout.
 */
class OrderCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Order $order,
    ) {}
}
