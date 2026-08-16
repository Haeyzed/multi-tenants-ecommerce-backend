<?php

declare(strict_types=1);

namespace App\Services\Tenant\Commerce;

use App\Events\CouponApplied;
use App\Models\Landlord\Tenant;
use App\Models\Tenant\Cart;
use App\Models\Tenant\Coupon;
use App\Models\Tenant\CouponUsage;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Order;
use App\Models\Tenant\Promotion;
use App\Services\Landlord\Feature\FeatureGate;
use App\Services\Tenant\Loyalty\LoyaltyService;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Applies coupons and promotions to a cart for checkout.
 */
class DiscountService
{
    public function __construct(
        private readonly CouponService $couponService,
        private readonly FeatureGate $featureGate,
        private readonly LoyaltyService $loyaltyService,
        private readonly PromotionService $promotionService,
    ) {}

    /**
     * Calculate discounts from promotions and optional coupon code.
     * Exclusive promotions win by priority; stackable promotions combine when no exclusive applies.
     */
    public function applyCouponsAndPromotions(
        Customer $customer,
        Cart $cart,
        ?string $couponCode,
        string $subtotal,
        string $shippingTotal,
    ): DiscountApplicationResult {
        $cart->loadMissing(['items.product']);

        if (Schema::hasTable('categories') && Schema::hasTable('category_product')) {
            $cart->loadMissing(['items.product.categories']);
        }

        $lineDiscounts = [];
        $promotionSnapshot = [];
        $discountTotal = '0.00';
        $adjustedShipping = Money::add($shippingTotal, '0');

        $applicable = $this->promotionService->evaluateForCart($cart);
        $exclusive = $applicable->first(fn ($promotion) => $promotion->is_exclusive);

        /** @var Collection<int, Promotion> $selectedPromotions */
        $selectedPromotions = $exclusive !== null
            ? collect([$exclusive])
            : $applicable->filter(fn ($promotion) => $promotion->is_stackable)->values();

        foreach ($selectedPromotions as $promotion) {
            $result = $this->promotionService->calculatePromotionDiscount($promotion, $cart);

            if ($result['free_shipping']) {
                $adjustedShipping = '0.00';
            }

            if (bccomp($result['amount'], '0', 2) > 0) {
                $discountTotal = Money::add($discountTotal, $result['amount']);
                $lineDiscounts = $this->mergeLineDiscounts($lineDiscounts, $result['line_discounts']);
            }

            $promotionSnapshot[] = [
                'id' => $promotion->id,
                'name' => $promotion->name,
                'slug' => $promotion->slug,
                'type' => $promotion->type->value,
                'amount' => $result['amount'],
                'free_shipping' => $result['free_shipping'],
            ];
        }

        $couponId = null;
        $appliedCouponCode = null;
        $couponDiscountTotal = '0.00';

        if ($couponCode !== null && trim($couponCode) !== '' && $exclusive === null) {
            $couponResult = $this->couponService->validateForCart($customer, $cart, $couponCode);
            $couponDiscountTotal = $couponResult->amount;
            $discountTotal = Money::add($discountTotal, $couponDiscountTotal);
            $lineDiscounts = $this->mergeLineDiscounts($lineDiscounts, $couponResult->lineDiscounts);
            $couponId = $couponResult->coupon?->id;
            $appliedCouponCode = $couponResult->coupon?->code;
        }

        if (bccomp($discountTotal, $subtotal, 2) > 0) {
            $discountTotal = Money::add($subtotal, '0');
        }

        return new DiscountApplicationResult(
            discountTotal: $discountTotal,
            couponDiscountTotal: $couponDiscountTotal,
            couponId: $couponId,
            couponCode: $appliedCouponCode,
            promotionSnapshot: $promotionSnapshot,
            lineDiscounts: $lineDiscounts,
            shippingTotal: $adjustedShipping,
        );
    }

    /**
     * Layer a loyalty point redemption on top of coupon and promotion discounts.
     *
     * The redemption is capped by whatever is still discountable on the cart so
     * points can never push the merchandise total below zero.
     */
    public function applyLoyaltyRedemption(
        Customer $customer,
        Cart $cart,
        DiscountApplicationResult $applied,
        string $subtotal,
        ?int $loyaltyPoints,
    ): DiscountApplicationResult {
        if ($loyaltyPoints === null || $loyaltyPoints <= 0) {
            return $applied;
        }

        $tenant = tenant();
        if (
            $tenant instanceof Tenant
            && $tenant->activeSubscription() !== null
            && ! $this->featureGate->allows('loyalty', $tenant)
        ) {
            throw ValidationException::withMessages([
                'loyalty_points' => 'Your current plan does not include the [loyalty] feature.',
            ]);
        }

        $discountableRemainder = Money::sub($subtotal, $applied->discountTotal);

        if (bccomp($discountableRemainder, '0', 2) <= 0) {
            return $applied;
        }

        $redemption = $this->loyaltyService->previewRedemption(
            $customer,
            $loyaltyPoints,
            $subtotal,
            $discountableRemainder,
        );

        if (! $redemption->isRedeemable()) {
            return $applied;
        }

        $lineDiscounts = $this->mergeLineDiscounts(
            $applied->lineDiscounts,
            $this->couponService->distributeDiscount($cart->items, $redemption->moneyValue),
        );

        return new DiscountApplicationResult(
            discountTotal: Money::add($applied->discountTotal, $redemption->moneyValue),
            couponDiscountTotal: $applied->couponDiscountTotal,
            couponId: $applied->couponId,
            couponCode: $applied->couponCode,
            promotionSnapshot: $applied->promotionSnapshot,
            lineDiscounts: $lineDiscounts,
            shippingTotal: $applied->shippingTotal,
            loyaltyPointsRedeemed: $redemption->points,
            loyaltyDiscountTotal: $redemption->moneyValue,
        );
    }

    /**
     * Write the loyalty ledger entry for a redemption once the order exists.
     */
    public function recordLoyaltyRedemption(Order $order, DiscountApplicationResult $discount): void
    {
        if ($discount->loyaltyPointsRedeemed <= 0) {
            return;
        }

        $customer = $order->customer ?? Customer::query()->find($order->customer_id);

        if ($customer === null) {
            return;
        }

        $this->loyaltyService->redeemForCheckout(
            $customer,
            $discount->loyaltyPointsRedeemed,
            (string) $order->subtotal,
            $order,
        );
    }

    /**
     * Preview coupon discount for cart without persisting.
     */
    public function previewCoupon(Customer $customer, Cart $cart, string $couponCode): DiscountResult
    {
        return $this->couponService->validateForCart($customer, $cart, $couponCode);
    }

    /**
     * Record coupon usage after order creation.
     */
    public function recordCouponUsage(Order $order, DiscountApplicationResult $discount): void
    {
        if ($discount->couponId === null || bccomp($discount->couponDiscountTotal, '0', 2) <= 0) {
            return;
        }

        CouponUsage::query()->create([
            'coupon_id' => $discount->couponId,
            'customer_id' => $order->customer_id,
            'order_id' => $order->id,
            'discount_amount' => $discount->couponDiscountTotal,
        ]);

        $coupon = Coupon::query()->find($discount->couponId);
        $customer = $order->customer ?? Customer::query()->find($order->customer_id);

        if ($coupon !== null && $customer !== null) {
            event(new CouponApplied($order, $customer, $coupon, $discount->couponDiscountTotal));
        }
    }

    /**
     * @param  array<int, string>  $existing
     * @param  array<int, string>  $additional
     * @return array<int, string>
     */
    protected function mergeLineDiscounts(array $existing, array $additional): array
    {
        foreach ($additional as $itemId => $amount) {
            $existing[$itemId] = Money::add($existing[$itemId] ?? '0.00', $amount);
        }

        return $existing;
    }
}
