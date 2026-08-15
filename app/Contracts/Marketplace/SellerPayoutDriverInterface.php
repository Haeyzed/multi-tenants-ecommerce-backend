<?php

declare(strict_types=1);

namespace App\Contracts\Marketplace;

use App\Models\Tenant\SellerPayout;

/**
 * Driver for executing seller payout transfers.
 */
interface SellerPayoutDriverInterface
{
    /**
     * Process a payout and return an external reference when available.
     */
    public function process(SellerPayout $payout): string;
}
