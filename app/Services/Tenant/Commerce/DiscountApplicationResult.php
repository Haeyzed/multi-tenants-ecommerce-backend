<?php

declare(strict_types=1);

namespace App\Services\Tenant\Commerce;

/**
 * Combined coupon and promotion discount application for checkout.
 */
final readonly class DiscountApplicationResult
{
    /**
     * @param  array<int, string>  $lineDiscounts  Cart item ID => discount amount
     * @param  list<array<string, mixed>>  $promotionSnapshot
     */
    public function __construct(
        public string $discountTotal,
        public string $couponDiscountTotal,
        public ?int $couponId,
        public ?string $couponCode,
        public array $promotionSnapshot,
        public array $lineDiscounts,
        public string $shippingTotal,
    ) {}
}
