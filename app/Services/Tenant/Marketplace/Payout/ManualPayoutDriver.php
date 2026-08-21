<?php

declare(strict_types=1);

namespace App\Services\Tenant\Marketplace\Payout;

use App\Contracts\Marketplace\SellerPayoutDriverInterface;
use App\Models\Tenant\SellerPayout;

/**
 * Manual/offline payout driver (marks paid without external transfer).
 */
class ManualPayoutDriver implements SellerPayoutDriverInterface
{
    /**
     * Process.
     *
     * @param  SellerPayout  $payout
     * @return string
     */
    public function process(SellerPayout $payout): string
    {
        return 'MANUAL-'.$payout->id;
    }
}
