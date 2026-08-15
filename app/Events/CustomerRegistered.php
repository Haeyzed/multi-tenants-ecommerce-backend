<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Tenant\Customer;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a new customer registers.
 */
class CustomerRegistered
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Customer $customer,
    ) {}
}
