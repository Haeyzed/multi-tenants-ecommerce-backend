<?php

declare(strict_types=1);

namespace App\Services\Tenant\Commerce;

use App\Enums\Tenant\Commerce\CouponType;
use App\Models\Tenant\Cart;
use App\Models\Tenant\CartItem;
use App\Models\Tenant\Coupon;
use App\Models\Tenant\Customer;
use App\Support\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * CRUD and validation for discount coupons.
 */
class CouponService
{
    /**
     * @param  array{search?: string|null, is_active?: bool|null, per_page?: int|null}  $params
     * @return LengthAwarePaginator<int, Coupon>
     */
    public function list(array $params = []): LengthAwarePaginator
    {
        $with = ['products'];
        if (Schema::hasTable('categories') && Schema::hasTable('coupon_category')) {
            $with[] = 'categories';
        }

        $query = Coupon::query()->with($with)->latest('id');

        if (array_key_exists('is_active', $params) && $params['is_active'] !== null) {
            $query->where('is_active', (bool) $params['is_active']);
        }

        if (! empty($params['search'])) {
            $search = (string) $params['search'];
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        return $query->paginate($this->perPage($params));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function store(array $data): Coupon
    {
        $coupon = Coupon::query()->create($this->attributesFromData($data));
        $this->syncRestrictions($coupon, $data);

        return $this->freshWithRelations($coupon);
    }

    public function show(Coupon $coupon): Coupon
    {
        return $this->freshWithRelations($coupon);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Coupon $coupon, array $data): Coupon
    {
        $coupon->fill($this->attributesFromData($data, $coupon));
        $coupon->save();
        $this->syncRestrictions($coupon, $data);

        return $this->freshWithRelations($coupon);
    }

    public function destroy(Coupon $coupon): void
    {
        $coupon->delete();
    }

    /**
     * Validate a coupon code against the customer's cart.
     *
     * @param  list<int>  $excludedCartItemIds  Cart item IDs excluded from coupon eligibility (e.g. non-stackable flash lines).
     *
     * @throws ValidationException
     */
    public function validateForCart(
        Customer $customer,
        Cart $cart,
        string $code,
        array $excludedCartItemIds = [],
    ): DiscountResult {
        if (! Schema::hasTable('coupons')) {
            throw ValidationException::withMessages([
                'coupon_code' => 'The coupon code is invalid.',
            ]);
        }

        $normalizedCode = Str::upper(trim($code));

        if ($normalizedCode === '') {
            throw ValidationException::withMessages([
                'coupon_code' => 'Coupon code is required.',
            ]);
        }

        $with = ['products'];
        if (Schema::hasTable('categories') && Schema::hasTable('coupon_category')) {
            $with[] = 'categories';
        }

        $coupon = Coupon::query()
            ->with($with)
            ->whereRaw('UPPER(code) = ?', [$normalizedCode])
            ->first();

        if ($coupon === null) {
            throw ValidationException::withMessages([
                'coupon_code' => 'The coupon code is invalid.',
            ]);
        }

        // Serialize usage-limit checks with redeem recording inside checkout transactions.
        $coupon = Coupon::query()
            ->with($with)
            ->whereKey($coupon->getKey())
            ->lockForUpdate()
            ->first() ?? $coupon;

        $this->assertCouponIsRedeemable($coupon, $customer);

        $eligibleItems = $this->eligibleCartItems($coupon, $cart, $excludedCartItemIds);

        if ($eligibleItems->isEmpty()) {
            throw ValidationException::withMessages([
                'coupon_code' => 'This coupon does not apply to any items in your cart.',
            ]);
        }

        $eligibleSubtotal = $this->sumItemSubtotals($eligibleItems);

        if (bccomp($eligibleSubtotal, (string) $coupon->minimum_order_amount, 2) < 0) {
            throw ValidationException::withMessages([
                'coupon_code' => 'Order subtotal does not meet the coupon minimum.',
            ]);
        }

        $discountAmount = $this->calculateDiscountAmount($coupon, $eligibleSubtotal);
        $lineDiscounts = $this->distributeDiscount($eligibleItems, $discountAmount);

        return new DiscountResult(
            amount: $discountAmount,
            coupon: $coupon,
            lineDiscounts: $lineDiscounts,
        );
    }

    /**
     * @throws ValidationException
     */
    public function assertCouponIsRedeemable(Coupon $coupon, Customer $customer): void
    {
        if (! $coupon->is_active) {
            throw ValidationException::withMessages([
                'coupon_code' => 'This coupon is not active.',
            ]);
        }

        $now = now();

        if ($coupon->starts_at !== null && $coupon->starts_at->isFuture()) {
            throw ValidationException::withMessages([
                'coupon_code' => 'This coupon is not yet available.',
            ]);
        }

        if ($coupon->expires_at !== null && $coupon->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'coupon_code' => 'This coupon has expired.',
            ]);
        }

        if ($coupon->usage_limit !== null) {
            $usageCount = $coupon->usages()->count();

            if ($usageCount >= $coupon->usage_limit) {
                throw ValidationException::withMessages([
                    'coupon_code' => 'This coupon has reached its usage limit.',
                ]);
            }
        }

        if ($coupon->usage_limit_per_customer !== null) {
            $customerUsageCount = $coupon->usages()
                ->where('customer_id', $customer->id)
                ->count();

            if ($customerUsageCount >= $coupon->usage_limit_per_customer) {
                throw ValidationException::withMessages([
                    'coupon_code' => 'You have already used this coupon the maximum number of times.',
                ]);
            }
        }

        if (
            Schema::hasColumn('coupons', 'customer_group_id')
            && $coupon->customer_group_id !== null
            && (int) $customer->customer_group_id !== (int) $coupon->customer_group_id
        ) {
            throw ValidationException::withMessages([
                'coupon_code' => 'This coupon is not available for your customer group.',
            ]);
        }
    }

    public function calculateDiscountAmount(Coupon $coupon, string $eligibleSubtotal): string
    {
        $discount = match ($coupon->type) {
            CouponType::Percentage => Money::percent($eligibleSubtotal, (string) $coupon->value),
            CouponType::Fixed => Money::add((string) $coupon->value, '0'),
        };

        if ($coupon->maximum_discount !== null && bccomp($discount, (string) $coupon->maximum_discount, 2) > 0) {
            $discount = Money::add((string) $coupon->maximum_discount, '0');
        }

        if (bccomp($discount, $eligibleSubtotal, 2) > 0) {
            $discount = Money::add($eligibleSubtotal, '0');
        }

        return $discount;
    }

    /**
     * @param  list<int>  $excludedCartItemIds
     * @return Collection<int, CartItem>
     */
    public function eligibleCartItems(Coupon $coupon, Cart $cart, array $excludedCartItemIds = []): Collection
    {
        $productIds = $coupon->relationLoaded('products')
            ? $coupon->products->pluck('id')->all()
            : $coupon->products()->pluck('products.id')->all();

        $categoryIds = [];
        if (Schema::hasTable('categories') && Schema::hasTable('coupon_category')) {
            $categoryIds = $coupon->relationLoaded('categories')
                ? $coupon->categories->pluck('id')->all()
                : $coupon->categories()->pluck('categories.id')->all();
        }

        $excluded = array_map('intval', $excludedCartItemIds);

        $items = $cart->items->filter(
            fn (CartItem $item): bool => ! in_array((int) $item->id, $excluded, true)
        )->values();

        if ($productIds === [] && $categoryIds === []) {
            return $items;
        }

        return $items->filter(function (CartItem $item) use ($productIds, $categoryIds): bool {
            if ($productIds !== [] && in_array((int) $item->product_id, $productIds, true)) {
                return true;
            }

            if ($categoryIds === []) {
                return false;
            }

            $item->loadMissing('product.categories');

            return $item->product?->categories
                ->pluck('id')
                ->intersect($categoryIds)
                ->isNotEmpty() ?? false;
        })->values();
    }

    /**
     * @param  Collection<int, CartItem>  $items
     */
    public function sumItemSubtotals(Collection $items): string
    {
        $subtotal = '0.00';

        foreach ($items as $item) {
            $subtotal = Money::add($subtotal, (string) $item->subtotal);
        }

        return $subtotal;
    }

    /**
     * @param  Collection<int, CartItem>  $items
     * @return array<int, string>
     */
    public function distributeDiscount(Collection $items, string $totalDiscount): array
    {
        if ($items->isEmpty() || bccomp($totalDiscount, '0', 2) <= 0) {
            return [];
        }

        $eligibleSubtotal = $this->sumItemSubtotals($items);
        $lineDiscounts = [];
        $distributed = '0.00';
        $lastItemId = null;

        foreach ($items as $item) {
            $lastItemId = $item->id;
            $share = bccomp($eligibleSubtotal, '0', 2) > 0
                ? Money::mul($totalDiscount, bcdiv((string) $item->subtotal, $eligibleSubtotal, 6))
                : '0.00';
            $lineDiscounts[$item->id] = $share;
            $distributed = Money::add($distributed, $share);
        }

        if ($lastItemId !== null && bccomp($distributed, $totalDiscount, 2) !== 0) {
            $lineDiscounts[$lastItemId] = Money::add(
                $lineDiscounts[$lastItemId],
                Money::sub($totalDiscount, $distributed),
            );
        }

        return $lineDiscounts;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function attributesFromData(array $data, ?Coupon $existing = null): array
    {
        $attributes = [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'type' => $data['type'],
            'value' => $data['value'],
            'minimum_order_amount' => $data['minimum_order_amount'] ?? 0,
            'maximum_discount' => $data['maximum_discount'] ?? null,
            'usage_limit' => $data['usage_limit'] ?? null,
            'usage_limit_per_customer' => $data['usage_limit_per_customer'] ?? null,
            'starts_at' => $data['starts_at'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ];

        if (Schema::hasColumn('coupons', 'customer_group_id')) {
            if (array_key_exists('customer_group_id', $data)) {
                $attributes['customer_group_id'] = $data['customer_group_id'];
            } elseif ($existing === null) {
                $attributes['customer_group_id'] = null;
            }
        }

        if (array_key_exists('code', $data)) {
            $attributes['code'] = Str::upper((string) $data['code']);
        } elseif ($existing === null) {
            $attributes['code'] = Str::upper((string) ($data['code'] ?? ''));
        }

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function syncRestrictions(Coupon $coupon, array $data): void
    {
        if (array_key_exists('product_ids', $data)) {
            $coupon->products()->sync($data['product_ids'] ?? []);
        }

        if (array_key_exists('category_ids', $data) && Schema::hasTable('categories') && Schema::hasTable('coupon_category')) {
            $coupon->categories()->sync($data['category_ids'] ?? []);
        }
    }

    /**
     * @param  array{per_page?: int|null}  $params
     */
    protected function perPage(array $params): int
    {
        return max(1, min((int) ($params['per_page'] ?? 15), 100));
    }

    protected function freshWithRelations(Coupon $coupon): Coupon
    {
        $with = ['products'];

        if (Schema::hasTable('categories') && Schema::hasTable('coupon_category')) {
            $with[] = 'categories';
        }

        return $coupon->fresh($with) ?? $coupon->load($with);
    }
}
