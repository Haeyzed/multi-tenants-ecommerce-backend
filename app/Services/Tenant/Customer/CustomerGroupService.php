<?php

declare(strict_types=1);

namespace App\Services\Tenant\Customer;

use App\Models\Tenant\CustomerGroup;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Tenant customer group classification operations.
 */
class CustomerGroupService
{
    /**
     * Paginate customer groups with search, filters, and sorts.
     *
     * @param  array{
     *     search?: string|null,
     *     is_active?: bool|null,
     *     sort?: string|null,
     *     per_page?: int|null
     * }  $params
     * @return LengthAwarePaginator<int, CustomerGroup>
     */
    public function list(array $params = []): LengthAwarePaginator
    {
        return CustomerGroup::query()
            ->withCount('customers')
            ->filter($params)
            ->applySort($params['sort'] ?? null)
            ->paginate($this->perPage($params));
    }

    /**
     * Customer group options for select inputs.
     *
     * @param  array{search?: string|null, is_active?: bool|null}  $params
     * @return Collection<int, array{label: string, value: int}>
     */
    public function options(array $params = []): Collection
    {
        return CustomerGroup::query()
            ->filter($params)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (CustomerGroup $group): array => [
                'label' => $group->name,
                'value' => $group->id,
            ])
            ->values();
    }

    /**
     * Create a customer group.
     *
     * @param  array{
     *     name: string,
     *     description?: string|null,
     *     is_active?: bool,
     *     sort_order?: int
     * }  $data
     * @return CustomerGroup
     */
    public function store(array $data): CustomerGroup
    {
        return CustomerGroup::query()->create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);
    }

    /**
     * Retrieve a customer group with membership count.
     *
     * @param  CustomerGroup  $group
     * @return CustomerGroup
     */
    public function show(CustomerGroup $group): CustomerGroup
    {
        return $group->loadCount('customers');
    }

    /**
     * Update a customer group.
     *
     * @param  CustomerGroup  $group
     * @param  array{
     *     name?: string,
     *     description?: string|null,
     *     is_active?: bool,
     *     sort_order?: int
     * }  $data
     * @return CustomerGroup
     */
    public function update(CustomerGroup $group, array $data): CustomerGroup
    {
        $group->fill($data);
        $group->save();

        return $group->fresh() ?? $group;
    }

    /**
     * Delete a customer group when no customers are assigned.
     *
     * @param  CustomerGroup  $group
     * @return void
     *
     * @throws ValidationException
     */
    public function destroy(CustomerGroup $group): void
    {
        if ($group->customers()->exists()) {
            throw ValidationException::withMessages([
                'customer_group' => 'Cannot delete a customer group that has assigned customers.',
            ]);
        }

        $group->delete();
    }

    /**
     * Resolve the page size for paginated listings.
     *
     * @param  array{per_page?: int|null}  $params
     * @return int
     */
    protected function perPage(array $params): int
    {
        return max(1, min((int) ($params['per_page'] ?? 15), 100));
    }
}
