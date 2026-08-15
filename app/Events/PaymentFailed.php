<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Landlord\PaymentTransaction;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentFailed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly PaymentTransaction $transaction,
        public readonly ?string $reason = null,
    ) {}
}
