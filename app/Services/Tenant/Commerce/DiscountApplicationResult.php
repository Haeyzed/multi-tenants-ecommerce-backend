<?php

declare(strict_types=1);

namespace App\Services\Tenant\Commerce;

/**
 * Combined coupon and promotion discount application for checkout.
 */
final readonly class DiscountApplicationResult
{
    /**
     * Create a new class instance.
     *
     * @param  string  $discountTotal
     * @param  string  $couponDiscountTotal
     * @param  ?int  $couponId
     * @param  ?string  $couponCode
     * @param  array<int, string>  $lineDiscounts  Cart item ID => discount amount
     * @param  list<array<string, mixed>>  $promotionSnapshot
     * @param  array  $lineDiscounts
     * @param  string  $shippingTotal
     * @param  int  $loyaltyPointsRedeemed
     * @param  string  $loyaltyDiscountTotal
     */
    public function __construct(
        public string $discountTotal,
        public string $couponDiscountTotal,
        public ?int $couponId,
        public ?string $couponCode,
        public array $promotionSnapshot,
        public array $lineDiscounts,
        public string $shippingTotal,
        public int $loyaltyPointsRedeemed = 0,
        public string $loyaltyDiscountTotal = '0.00',
    ) {}
}
