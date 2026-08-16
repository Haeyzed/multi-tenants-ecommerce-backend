<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Tenant\Seller;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a seller verification is rejected.
 */
class SellerRejected
{
    use Dispatchable, SerializesModels;

    public function __construct(public Seller $seller) {}
}
