<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Tenant\Seller;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a seller is suspended.
 */
class SellerSuspended
{
    use Dispatchable, SerializesModels;

    public function __construct(public Seller $seller) {}
}
