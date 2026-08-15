<?php

declare(strict_types=1);

namespace App\Services\Tenant\Commerce;

use App\Models\Tenant\Coupon;

/**
 * Result of validating a coupon against a cart.
 */
final readonly class DiscountResult
{
    /**
     * @param  array<int, string>  $lineDiscounts  Cart item ID => discount amount
     */
    public function __construct(
        public string $amount,
        public ?Coupon $coupon = null,
        public array $lineDiscounts = [],
        public ?string $message = null,
    ) {}

    public function isValid(): bool
    {
        return $this->coupon !== null && bccomp($this->amount, '0', 2) >= 0;
    }
}
