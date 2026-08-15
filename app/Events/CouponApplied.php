<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Tenant\Coupon;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CouponApplied
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Order $order,
        public Customer $customer,
        public Coupon $coupon,
        public string $discountAmount,
    ) {}
}
