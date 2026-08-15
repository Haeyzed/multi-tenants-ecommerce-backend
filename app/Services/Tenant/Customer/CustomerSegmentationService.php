<?php

declare(strict_types=1);

namespace App\Services\Tenant\Customer;

use App\Enums\Tenant\Commerce\CartStatus;
use App\Enums\Tenant\Commerce\OrderStatus;
use App\Enums\Tenant\Customer\CustomerSegmentRule;
use App\Models\Tenant\Customer;
use App\Models\Tenant\CustomerSegment;
use App\Models\Tenant\Order;
use App\Support\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;

/**
 * Evaluates rule-based customer segments directly against customer data.
 *
 * Membership is computed on demand rather than materialised, so segments always
 * reflect current order, wishlist, and cart state.
 */
class CustomerSegmentationService
{
    /**
     * Paginate the configured segments.
     *
     * @param  array{search?: string|null, is_active?: bool|null, sort?: string|null, per_page?: int|null}  $params
     * @return LengthAwarePaginator<int, CustomerSegment>
     */
    public function list(array $params = []): LengthAwarePaginator
    {
        return CustomerSegment::query()
            ->filter($params)
            ->applySort($params['sort'] ?? null)
            ->paginate(max(1, min((int) ($params['per_page'] ?? 15), 100)));
    }

    /**
     * Resolve an active segment by slug.
     */
    public function findBySlug(string $slug): ?CustomerSegment
    {
        return CustomerSegment::query()->where('slug', $slug)->first();
    }

    /**
     * Slugs of every active segment the customer currently belongs to.
     *
     * @return list<string>
     */
    public function evaluate(Customer $customer): array
    {
        return $this->activeSegments()
            ->filter(fn (CustomerSegment $segment): bool => $this->matches($customer, $segment))
            ->map(fn (CustomerSegment $segment): string => $segment->slug)
            ->values()
            ->all();
    }

    /**
     * Whether a single customer satisfies a segment's rules.
     */
    public function matches(Customer $customer, CustomerSegment $segment): bool
    {
        return $this->query($segment)->whereKey($customer->getKey())->exists();
    }

    /**
     * Paginate the customers currently inside a segment.
     *
     * @param  array{search?: string|null, sort?: string|null, per_page?: int|null}  $params
     * @return LengthAwarePaginator<int, Customer>
     */
    public function customers(CustomerSegment $segment, array $params = []): LengthAwarePaginator
    {
        return $this->query($segment)
            ->filter($params)
            ->applySort($params['sort'] ?? null)
            ->paginate(max(1, min((int) ($params['per_page'] ?? 15), 100)));
    }

    /**
     * Number of customers currently inside a segment.
     */
    public function count(CustomerSegment $segment): int
    {
        return $this->query($segment)->count();
    }

    /**
     * Build the customer query that expresses a segment's rules.
     *
     * @return Builder<Customer>
     */
    public function query(CustomerSegment $segment): Builder
    {
        $conditions = $segment->conditions();
        $query = Customer::query();

        if ($conditions === []) {
            return $query;
        }

        $matchAny = $segment->matchMode() === 'any';

        return $query->where(function (Builder $query) use ($conditions, $matchAny): void {
            foreach ($conditions as $condition) {
                $rule = CustomerSegmentRule::tryFrom((string) $condition['type']);

                if ($rule === null) {
                    continue;
                }

                $value = $condition['value'] ?? $rule->defaultValue();

                if ($matchAny) {
                    $query->orWhere(fn (Builder $query) => $this->applyRule($query, $rule, $value));

                    continue;
                }

                $this->applyRule($query, $rule, $value);
            }
        });
    }

    /**
     * Active segments ordered for display.
     *
     * @return Collection<int, CustomerSegment>
     */
    protected function activeSegments(): Collection
    {
        return CustomerSegment::query()
            ->where('is_active', true)
            ->applySort(null)
            ->get();
    }

    /**
     * Translate one rule into query constraints.
     *
     * @param  Builder<Customer>  $query
     */
    protected function applyRule(Builder $query, CustomerSegmentRule $rule, mixed $value): void
    {
        match ($rule) {
            CustomerSegmentRule::NewCustomer => $query->whereDoesntHave('orders', $this->countableOrder(...)),
            CustomerSegmentRule::ReturningCustomer => $query->whereHas('orders', $this->countableOrder(...)),
            CustomerSegmentRule::FrequentBuyer => $query->whereHas(
                'orders',
                $this->countableOrder(...),
                '>=',
                max(1, (int) $value),
            ),
            CustomerSegmentRule::HighValue => $query->whereIn(
                'id',
                $this->lifetimeSpendAtLeast(Money::add((string) $value, '0.00')),
            ),
            CustomerSegmentRule::Inactive => $query->whereHas('orders', $this->countableOrder(...))
                ->whereDoesntHave('orders', function (Builder $query) use ($value): void {
                    $this->countableOrder($query);
                    $query->where('placed_at', '>=', now()->subDays(max(1, (int) $value)));
                }),
            CustomerSegmentRule::WishlistCustomer => $query->whereHas(
                'wishlist',
                fn (Builder $query) => $query->whereHas('items'),
            ),
            CustomerSegmentRule::AbandonedCartCustomer => $query->whereHas(
                'carts',
                fn (Builder $query) => $query->where('status', CartStatus::Abandoned->value),
            ),
        };
    }

    /**
     * Orders that count towards customer value (cancelled orders are ignored).
     *
     * @param  Builder<Order>  $query
     */
    protected function countableOrder(Builder $query): void
    {
        $query->where('status', '!=', OrderStatus::Cancelled->value);
    }

    /**
     * Sub-select of customer ids whose lifetime spend reaches the threshold.
     */
    protected function lifetimeSpendAtLeast(string $threshold): QueryBuilder
    {
        return Order::query()
            ->select('customer_id')
            ->where('status', '!=', OrderStatus::Cancelled->value)
            ->groupBy('customer_id')
            // Cast keeps the comparison numeric; a bare string binding is compared as
            // text on SQLite and would never match.
            ->havingRaw('SUM(grand_total) >= CAST(? AS DECIMAL(14,2))', [$threshold])
            ->toBase();
    }
}
