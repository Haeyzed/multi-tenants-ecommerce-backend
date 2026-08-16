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
     * Resolve a segment by slug.
     */
    public function findBySlug(string $slug): ?CustomerSegment
    {
        return CustomerSegment::query()->where('slug', $slug)->first();
    }

    /**
     * Create a customer segment.
     *
     * @param  array{
     *     name: string,
     *     description?: string|null,
     *     match?: string,
     *     conditions: list<array{type: string, value?: mixed}>,
     *     is_active?: bool,
     *     sort_order?: int
     * }  $data
     */
    public function store(array $data): CustomerSegment
    {
        return CustomerSegment::query()->create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'rules' => [
                'match' => ($data['match'] ?? 'all') === 'any' ? 'any' : 'all',
                'conditions' => array_values($data['conditions']),
            ],
            'is_active' => $data['is_active'] ?? true,
            'sort_order' => $data['sort_order'] ?? 0,
            'customers_count' => 0,
        ]);
    }

    /**
     * Retrieve a segment with a live membership count.
     */
    public function show(CustomerSegment $segment): CustomerSegment
    {
        $segment->setAttribute('customers_count', $this->count($segment));

        return $segment;
    }

    /**
     * Update a customer segment.
     *
     * @param  array{
     *     name?: string,
     *     description?: string|null,
     *     match?: string,
     *     conditions?: list<array{type: string, value?: mixed}>,
     *     is_active?: bool,
     *     sort_order?: int
     * }  $data
     */
    public function update(CustomerSegment $segment, array $data): CustomerSegment
    {
        if (array_key_exists('name', $data)) {
            $segment->name = $data['name'];
        }

        if (array_key_exists('description', $data)) {
            $segment->description = $data['description'];
        }

        if (array_key_exists('is_active', $data)) {
            $segment->is_active = (bool) $data['is_active'];
        }

        if (array_key_exists('sort_order', $data)) {
            $segment->sort_order = (int) $data['sort_order'];
        }

        if (array_key_exists('conditions', $data) || array_key_exists('match', $data)) {
            $rules = $segment->rules ?? ['match' => 'all', 'conditions' => []];
            $rules['match'] = ($data['match'] ?? $segment->matchMode()) === 'any' ? 'any' : 'all';

            if (array_key_exists('conditions', $data)) {
                $rules['conditions'] = array_values($data['conditions']);
            }

            $segment->rules = $rules;
        }

        $segment->save();

        return $segment->fresh() ?? $segment;
    }

    /**
     * Delete a customer segment.
     */
    public function destroy(CustomerSegment $segment): void
    {
        $segment->delete();
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
