<?php

declare(strict_types=1);

namespace App\Services\Tenant\Customer;

use App\Enums\Tenant\Commerce\CartStatus;
use App\Enums\Tenant\Commerce\OrderStatus;
use App\Enums\Tenant\Customer\CustomerSegmentRule;
use App\Jobs\MaterializeCustomerSegmentMembershipJob;
use App\Models\Tenant\Customer;
use App\Models\Tenant\CustomerSegment;
use App\Models\Tenant\CustomerSegmentMember;
use App\Models\Tenant\Order;
use App\Support\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Evaluates rule-based customer segments and materializes membership for fast reads.
 *
 * Rule evaluation remains the source of truth when refreshing; list/count/evaluate
 * prefer the membership pivot after materialization.
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
     *
     * @param  string  $slug
     * @return ?CustomerSegment
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
     * @return CustomerSegment
     */
    public function store(array $data): CustomerSegment
    {
        $segment = CustomerSegment::query()->create([
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

        $this->dispatchMaterialize($segment);

        return $segment;
    }

    /**
     * Retrieve a segment with a membership count (materialized when available).
     *
     * @param  CustomerSegment  $segment
     * @return CustomerSegment
     */
    public function show(CustomerSegment $segment): CustomerSegment
    {
        $segment->setAttribute('customers_count', $this->count($segment));

        return $segment;
    }

    /**
     * Update a customer segment.
     *
     * @param  CustomerSegment  $segment
     * @param  array{
     *     name?: string,
     *     description?: string|null,
     *     match?: string,
     *     conditions?: list<array{type: string, value?: mixed}>,
     *     is_active?: bool,
     *     sort_order?: int
     * }  $data
     * @return CustomerSegment
     */
    public function update(CustomerSegment $segment, array $data): CustomerSegment
    {
        $rulesChanged = array_key_exists('conditions', $data) || array_key_exists('match', $data);

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

        if ($rulesChanged) {
            $rules = $segment->rules ?? ['match' => 'all', 'conditions' => []];
            $rules['match'] = ($data['match'] ?? $segment->matchMode()) === 'any' ? 'any' : 'all';

            if (array_key_exists('conditions', $data)) {
                $rules['conditions'] = array_values($data['conditions']);
            }

            $segment->rules = $rules;
        }

        $segment->save();

        if ($rulesChanged || array_key_exists('is_active', $data)) {
            $this->dispatchMaterialize($segment);
        }

        return $segment->fresh() ?? $segment;
    }

    /**
     * Delete a customer segment.
     *
     * @param  CustomerSegment  $segment
     * @return void
     */
    public function destroy(CustomerSegment $segment): void
    {
        $segment->delete();
    }

    /**
     * Rebuild membership rows for a segment from current rule evaluation.
     *
     * @param  CustomerSegment  $segment
     * @return CustomerSegment
     */
    public function materialize(CustomerSegment $segment): CustomerSegment
    {
        return DB::transaction(function () use ($segment): CustomerSegment {
            $matchingIds = $this->query($segment)->pluck('id')->map(fn ($id): int => (int) $id)->all();
            $now = now();

            $stale = CustomerSegmentMember::query()->where('customer_segment_id', $segment->id);

            if ($matchingIds !== []) {
                $stale->whereNotIn('customer_id', $matchingIds);
            }

            $stale->delete();

            foreach (array_chunk($matchingIds, 500) as $chunk) {
                $rows = [];

                foreach ($chunk as $customerId) {
                    $rows[] = [
                        'customer_segment_id' => $segment->id,
                        'customer_id' => $customerId,
                        'entered_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                CustomerSegmentMember::query()->upsert(
                    $rows,
                    ['customer_segment_id', 'customer_id'],
                    ['updated_at'],
                );
            }

            $segment->forceFill([
                'customers_count' => count($matchingIds),
                'membership_refreshed_at' => $now,
            ])->saveQuietly();

            return $segment->fresh() ?? $segment;
        });
    }

    /**
     * Slugs of every active segment the customer currently belongs to.
     *
     * @param  Customer  $customer
     * @return list<string>
     */
    public function evaluate(Customer $customer): array
    {
        if ($this->hasAnyMaterializedMembership()) {
            return CustomerSegmentMember::query()
                ->where('customer_id', $customer->id)
                ->whereHas('segment', fn (Builder $query) => $query->where('is_active', true))
                ->with('segment:id,slug')
                ->get()
                ->pluck('segment.slug')
                ->filter()
                ->values()
                ->all();
        }

        return $this->activeSegments()
            ->filter(fn (CustomerSegment $segment): bool => $this->matchesLive($customer, $segment))
            ->map(fn (CustomerSegment $segment): string => $segment->slug)
            ->values()
            ->all();
    }

    /**
     * Whether a single customer satisfies a segment (prefer membership when refreshed).
     *
     * @param  Customer  $customer
     * @param  CustomerSegment  $segment
     * @return bool
     */
    public function matches(Customer $customer, CustomerSegment $segment): bool
    {
        if ($segment->membership_refreshed_at !== null) {
            return CustomerSegmentMember::query()
                ->where('customer_segment_id', $segment->id)
                ->where('customer_id', $customer->id)
                ->exists();
        }

        return $this->matchesLive($customer, $segment);
    }

    /**
     * Live rule evaluation without reading the membership pivot.
     *
     * @param  Customer  $customer
     * @param  CustomerSegment  $segment
     * @return bool
     */
    public function matchesLive(Customer $customer, CustomerSegment $segment): bool
    {
        return $this->query($segment)->whereKey($customer->getKey())->exists();
    }

    /**
     * Paginate the customers currently inside a segment (materialized when available).
     *
     * @param  CustomerSegment  $segment
     * @param  array{search?: string|null, sort?: string|null, per_page?: int|null}  $params
     * @return LengthAwarePaginator<int, Customer>
     */
    public function customers(CustomerSegment $segment, array $params = []): LengthAwarePaginator
    {
        $base = $segment->membership_refreshed_at !== null
            ? Customer::query()->whereIn(
                'id',
                CustomerSegmentMember::query()
                    ->select('customer_id')
                    ->where('customer_segment_id', $segment->id),
            )
            : $this->query($segment);

        return $base
            ->filter($params)
            ->applySort($params['sort'] ?? null)
            ->paginate(max(1, min((int) ($params['per_page'] ?? 15), 100)));
    }

    /**
     * Number of customers currently inside a segment.
     *
     * @param  CustomerSegment  $segment
     * @return int
     */
    public function count(CustomerSegment $segment): int
    {
        if ($segment->membership_refreshed_at !== null) {
            return (int) $segment->customers_count;
        }

        return $this->query($segment)->count();
    }

    /**
     * Build the customer query that expresses a segment's rules.
     *
     * @param  CustomerSegment  $segment
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
     * Queue membership materialization for a segment when tenancy is initialized.
     *
     * @param  CustomerSegment  $segment
     * @return void
     */
    protected function dispatchMaterialize(CustomerSegment $segment): void
    {
        $tenantId = tenant('id');

        if (! is_string($tenantId) || $tenantId === '') {
            return;
        }

        MaterializeCustomerSegmentMembershipJob::dispatch($tenantId, (int) $segment->id);
    }

    /**
     * Whether any membership rows exist for the tenant.
     *
     * @return bool
     */
    protected function hasAnyMaterializedMembership(): bool
    {
        if (! Schema::hasTable('customer_segment_members')) {
            return false;
        }

        return CustomerSegmentMember::query()->exists();
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
     * @param  CustomerSegmentRule  $rule
     * @param  mixed  $value
     * @return void
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
     * @return void
     */
    protected function countableOrder(Builder $query): void
    {
        $query->where('status', '!=', OrderStatus::Cancelled->value);
    }

    /**
     * Sub-select of customer ids whose lifetime spend reaches the threshold.
     *
     * @param  string  $threshold
     * @return QueryBuilder
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
