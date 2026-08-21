<?php

declare(strict_types=1);

namespace App\Services\Tenant\Marketplace;

use App\Models\Tenant\SellerGroup;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Tenant seller group classification operations.
 */
class SellerGroupService
{
    /**
     * Paginate seller groups with search, filters, and sorts.
     *
     * @param  array{
     *     search?: string|null,
     *     is_active?: bool|null,
     *     sort?: string|null,
     *     per_page?: int|null
     * }  $params
     * @return LengthAwarePaginator<int, SellerGroup>
     */
    public function list(array $params = []): LengthAwarePaginator
    {
        return SellerGroup::query()
            ->withCount('sellers')
            ->filter($params)
            ->applySort($params['sort'] ?? null)
            ->paginate($this->perPage($params));
    }

    /**
     * Seller group options for select inputs.
     *
     * @param  array{search?: string|null, is_active?: bool|null}  $params
     * @return Collection<int, array{label: string, value: int}>
     */
    public function options(array $params = []): Collection
    {
        return SellerGroup::query()
            ->filter($params)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (SellerGroup $group): array => [
                'label' => $group->name,
                'value' => $group->id,
            ])
            ->values();
    }

    /**
     * Create a seller group.
     *
     * @param  array{
     *     name: string,
     *     description?: string|null,
     *     commission_type?: string|null,
     *     commission_rate?: string|null,
     *     commission_fixed_amount?: string|null,
     *     is_active?: bool,
     *     sort_order?: int
     * }  $data
     * @return SellerGroup
     */
    public function store(array $data): SellerGroup
    {
        return SellerGroup::query()->create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'commission_type' => $data['commission_type'] ?? null,
            'commission_rate' => $data['commission_rate'] ?? null,
            'commission_fixed_amount' => $data['commission_fixed_amount'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);
    }

    /**
     * Retrieve a seller group with membership count.
     *
     * @param  SellerGroup  $group
     * @return SellerGroup
     */
    public function show(SellerGroup $group): SellerGroup
    {
        return $group->loadCount('sellers');
    }

    /**
     * Update a seller group.
     *
     * @param  SellerGroup  $group
     * @param  array{
     *     name?: string,
     *     description?: string|null,
     *     commission_type?: string|null,
     *     commission_rate?: string|null,
     *     commission_fixed_amount?: string|null,
     *     is_active?: bool,
     *     sort_order?: int
     * }  $data
     * @return SellerGroup
     */
    public function update(SellerGroup $group, array $data): SellerGroup
    {
        $group->fill($data);
        $group->save();

        return $group->fresh() ?? $group;
    }

    /**
     * Delete a seller group when no sellers are assigned.
     *
     * @param  SellerGroup  $group
     * @return void
     *
     * @throws ValidationException
     */
    public function destroy(SellerGroup $group): void
    {
        if ($group->sellers()->exists()) {
            throw ValidationException::withMessages([
                'seller_group' => 'Cannot delete a seller group that has assigned sellers.',
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
