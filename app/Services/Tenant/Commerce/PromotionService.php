<?php

declare(strict_types=1);

namespace App\Services\Tenant\Commerce;

use App\Enums\Tenant\Commerce\PromotionType;
use App\Models\Tenant\Cart;
use App\Models\Tenant\CartItem;
use App\Models\Tenant\Promotion;
use App\Support\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * CRUD and cart evaluation for automatic promotions.
 */
class PromotionService
{
    public function __construct(
        private readonly CouponService $couponService,
    ) {}

    /**
     * @param  array{search?: string|null, is_active?: bool|null, type?: string|null, per_page?: int|null}  $params
     * @return LengthAwarePaginator<int, Promotion>
     */
    public function list(array $params = []): LengthAwarePaginator
    {
        $query = Promotion::query()->with(['products', 'categories'])->latest('id');

        if (array_key_exists('is_active', $params) && $params['is_active'] !== null) {
            $query->where('is_active', (bool) $params['is_active']);
        }

        if (! empty($params['type'])) {
            $query->where('type', (string) $params['type']);
        }

        if (! empty($params['search'])) {
            $search = (string) $params['search'];
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        return $query->paginate($this->perPage($params));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function store(array $data): Promotion
    {
        $promotion = Promotion::query()->create($this->attributesFromData($data));
        $this->syncRestrictions($promotion, $data);

        return $promotion->fresh(['products', 'categories']) ?? $promotion;
    }

    public function show(Promotion $promotion): Promotion
    {
        return $promotion->load(['products', 'categories']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Promotion $promotion, array $data): Promotion
    {
        $promotion->fill($this->attributesFromData($data, $promotion));
        $promotion->save();
        $this->syncRestrictions($promotion, $data);

        return $promotion->fresh(['products', 'categories']) ?? $promotion;
    }

    public function destroy(Promotion $promotion): void
    {
        $promotion->delete();
    }

    /**
     * Return promotions applicable to the cart, ordered by priority descending.
     *
     * @return Collection<int, Promotion>
     */
    public function evaluateForCart(Cart $cart): Collection
    {
        if (! Schema::hasTable('promotions')) {
            return collect();
        }

        $subtotal = $this->couponService->sumItemSubtotals($cart->items);
        $now = now();

        $with = ['products'];
        if (Schema::hasTable('categories') && Schema::hasTable('promotion_category')) {
            $with[] = 'categories';
        }

        return Promotion::query()
            ->with($with)
            ->where('is_active', true)
            ->where(function ($query) use ($now): void {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($query) use ($now): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            })
            ->orderByDesc('priority')
            ->orderByDesc('id')
            ->get()
            ->filter(function (Promotion $promotion) use ($cart, $subtotal): bool {
                if (bccomp($subtotal, (string) $promotion->min_order_amount, 2) < 0) {
                    return false;
                }

                return $this->eligibleCartItems($promotion, $cart)->isNotEmpty()
                    || $promotion->type === PromotionType::FreeShipping;
            })
            ->values();
    }

    /**
     * Calculate promotion discount amount and line allocations.
     *
     * @return array{amount: string, line_discounts: array<int, string>, free_shipping: bool}
     */
    public function calculatePromotionDiscount(Promotion $promotion, Cart $cart): array
    {
        $eligibleItems = $this->eligibleCartItems($promotion, $cart);
        $eligibleSubtotal = $this->couponService->sumItemSubtotals($eligibleItems);

        return match ($promotion->type) {
            PromotionType::PercentageOffOrder => [
                'amount' => $this->capDiscount(
                    Money::percent($eligibleSubtotal, (string) $promotion->value),
                    $eligibleSubtotal,
                    $promotion->max_discount,
                ),
                'line_discounts' => $this->couponService->distributeDiscount(
                    $eligibleItems,
                    $this->capDiscount(
                        Money::percent($eligibleSubtotal, (string) $promotion->value),
                        $eligibleSubtotal,
                        $promotion->max_discount,
                    ),
                ),
                'free_shipping' => false,
            ],
            PromotionType::FixedOffOrder => [
                'amount' => $this->capDiscount(
                    Money::add((string) $promotion->value, '0'),
                    $eligibleSubtotal,
                    $promotion->max_discount,
                ),
                'line_discounts' => $this->couponService->distributeDiscount(
                    $eligibleItems,
                    $this->capDiscount(
                        Money::add((string) $promotion->value, '0'),
                        $eligibleSubtotal,
                        $promotion->max_discount,
                    ),
                ),
                'free_shipping' => false,
            ],
            PromotionType::PercentageOffCategory => [
                'amount' => $this->capDiscount(
                    Money::percent($eligibleSubtotal, (string) $promotion->value),
                    $eligibleSubtotal,
                    $promotion->max_discount,
                ),
                'line_discounts' => $this->couponService->distributeDiscount(
                    $eligibleItems,
                    $this->capDiscount(
                        Money::percent($eligibleSubtotal, (string) $promotion->value),
                        $eligibleSubtotal,
                        $promotion->max_discount,
                    ),
                ),
                'free_shipping' => false,
            ],
            PromotionType::FreeShipping => [
                'amount' => '0.00',
                'line_discounts' => [],
                'free_shipping' => true,
            ],
            PromotionType::BuyXGetYSimple => $this->calculateBuyXGetY($promotion, $cart),
        };
    }

    /**
     * @return Collection<int, CartItem>
     */
    public function eligibleCartItems(Promotion $promotion, Cart $cart): Collection
    {
        if ($promotion->type === PromotionType::FreeShipping) {
            return $cart->items;
        }

        $productIds = $promotion->products->pluck('id')->all();
        $categoryIds = $promotion->categories->pluck('id')->all();

        if ($productIds === [] && $categoryIds === []) {
            return $cart->items;
        }

        return $cart->items->filter(function (CartItem $item) use ($productIds, $categoryIds): bool {
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
     * @return array{amount: string, line_discounts: array<int, string>, free_shipping: bool}
     */
    protected function calculateBuyXGetY(Promotion $promotion, Cart $cart): array
    {
        $metadata = $promotion->metadata ?? [];
        $buyQuantity = max(1, (int) ($metadata['buy_quantity'] ?? 1));
        $getQuantity = max(1, (int) ($metadata['get_quantity'] ?? 1));
        $productId = isset($metadata['product_id']) ? (int) $metadata['product_id'] : null;

        $eligibleItems = $cart->items->filter(function (CartItem $item) use ($productId): bool {
            return $productId === null || (int) $item->product_id === $productId;
        });

        $totalQuantity = $eligibleItems->sum('quantity');
        $setSize = $buyQuantity + $getQuantity;
        $freeUnits = $setSize > 0 ? intdiv($totalQuantity, $setSize) * $getQuantity : 0;

        if ($freeUnits <= 0 || $eligibleItems->isEmpty()) {
            return ['amount' => '0.00', 'line_discounts' => [], 'free_shipping' => false];
        }

        $sorted = $eligibleItems->sortBy('unit_price')->values();
        $remainingFree = $freeUnits;
        $lineDiscounts = [];
        $totalDiscount = '0.00';

        foreach ($sorted as $item) {
            if ($remainingFree <= 0) {
                break;
            }

            $units = min($remainingFree, $item->quantity);
            $discount = Money::mul((string) $item->unit_price, (string) $units);
            $lineDiscounts[$item->id] = Money::add($lineDiscounts[$item->id] ?? '0.00', $discount);
            $totalDiscount = Money::add($totalDiscount, $discount);
            $remainingFree -= $units;
        }

        return [
            'amount' => $totalDiscount,
            'line_discounts' => $lineDiscounts,
            'free_shipping' => false,
        ];
    }

    protected function capDiscount(string $discount, string $eligibleSubtotal, ?string $maxDiscount): string
    {
        if ($maxDiscount !== null && bccomp($discount, $maxDiscount, 2) > 0) {
            $discount = Money::add($maxDiscount, '0');
        }

        if (bccomp($discount, $eligibleSubtotal, 2) > 0) {
            $discount = Money::add($eligibleSubtotal, '0');
        }

        return $discount;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function attributesFromData(array $data, ?Promotion $existing = null): array
    {
        $attributes = [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'type' => $data['type'],
            'value' => $data['value'] ?? 0,
            'min_order_amount' => $data['min_order_amount'] ?? 0,
            'max_discount' => $data['max_discount'] ?? null,
            'priority' => $data['priority'] ?? 0,
            'is_exclusive' => $data['is_exclusive'] ?? false,
            'is_stackable' => $data['is_stackable'] ?? true,
            'is_active' => $data['is_active'] ?? true,
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'metadata' => $data['metadata'] ?? null,
        ];

        if (array_key_exists('slug', $data)) {
            $attributes['slug'] = Str::slug((string) $data['slug']);
        } elseif ($existing === null) {
            $attributes['slug'] = Str::slug((string) ($data['slug'] ?? $data['name'] ?? ''));
        }

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function syncRestrictions(Promotion $promotion, array $data): void
    {
        if (array_key_exists('product_ids', $data)) {
            $promotion->products()->sync($data['product_ids'] ?? []);
        }

        if (array_key_exists('category_ids', $data)) {
            $promotion->categories()->sync($data['category_ids'] ?? []);
        }
    }

    /**
     * @param  array{per_page?: int|null}  $params
     */
    protected function perPage(array $params): int
    {
        return max(1, min((int) ($params['per_page'] ?? 15), 100));
    }
}
