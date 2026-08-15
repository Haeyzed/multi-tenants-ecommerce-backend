<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Tenant\LoyaltyAccount;
use App\Models\Tenant\LoyaltyTransaction;
use App\Models\Tenant\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LoyaltyPointsEarned
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public LoyaltyAccount $account,
        public LoyaltyTransaction $transaction,
        public ?Order $order = null,
    ) {}
}
