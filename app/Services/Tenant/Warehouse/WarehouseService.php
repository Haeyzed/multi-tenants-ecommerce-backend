<?php

declare(strict_types=1);

namespace App\Services\Tenant\Warehouse;

use App\Models\Tenant\Warehouse;
use App\Models\Tenant\WarehouseLocation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Tenant warehouse and location catalog operations.
 */
class WarehouseService
{
    /**
     * Paginate warehouses with search, filters, and sorts.
     *
     * @param  array{
     *     search?: string|null,
     *     is_active?: bool|null,
     *     is_default?: bool|null,
     *     sort?: string|null,
     *     per_page?: int|null
     * }  $params
     * @return LengthAwarePaginator<int, Warehouse>
     */
    public function list(array $params = []): LengthAwarePaginator
    {
        return Warehouse::query()
            ->filter($params)
            ->applySort($params['sort'] ?? null)
            ->paginate($this->perPage($params));
    }

    /**
     * Warehouse options for select inputs.
     *
     * @param  array{search?: string|null, is_active?: bool|null, is_default?: bool|null}  $params
     * @return Collection<int, array{label: string, value: int}>
     */
    public function options(array $params = []): Collection
    {
        return Warehouse::query()
            ->filter($params)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn (Warehouse $warehouse): array => [
                'label' => $warehouse->name,
                'value' => $warehouse->id,
            ])
            ->values();
    }

    /**
     * Create a warehouse and unset other defaults when marked default.
     *
     * @param  array{
     *     name: string,
     *     code: string,
     *     description?: string|null,
     *     address?: string|null,
     *     country_id?: int|null,
     *     state_id?: int|null,
     *     city_id?: int|null,
     *     phone?: string|null,
     *     email?: string|null,
     *     is_active?: bool,
     *     is_default?: bool,
     *     sort_order?: int
     * }  $data
     */
    public function store(array $data): Warehouse
    {
        return DB::transaction(function () use ($data): Warehouse {
            if (($data['is_default'] ?? false) === true) {
                Warehouse::query()->where('is_default', true)->update(['is_default' => false]);
            }

            return Warehouse::query()->create([
                'name' => $data['name'],
                'code' => $data['code'],
                'description' => $data['description'] ?? null,
                'address' => $data['address'] ?? null,
                'country_id' => $data['country_id'] ?? null,
                'state_id' => $data['state_id'] ?? null,
                'city_id' => $data['city_id'] ?? null,
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'is_default' => $data['is_default'] ?? false,
                'sort_order' => $data['sort_order'] ?? 0,
            ]);
        });
    }

    /**
     * Retrieve a warehouse with locations loaded.
     */
    public function show(Warehouse $warehouse): Warehouse
    {
        return $warehouse->load('locations');
    }

    /**
     * Update a warehouse and unset other defaults when marked default.
     *
     * @param  array{
     *     name?: string,
     *     code?: string,
     *     description?: string|null,
     *     address?: string|null,
     *     country_id?: int|null,
     *     state_id?: int|null,
     *     city_id?: int|null,
     *     phone?: string|null,
     *     email?: string|null,
     *     is_active?: bool,
     *     is_default?: bool,
     *     sort_order?: int
     * }  $data
     */
    public function update(Warehouse $warehouse, array $data): Warehouse
    {
        return DB::transaction(function () use ($warehouse, $data): Warehouse {
            if (($data['is_default'] ?? false) === true) {
                Warehouse::query()
                    ->where('is_default', true)
                    ->whereKeyNot($warehouse->getKey())
                    ->update(['is_default' => false]);
            }

            $warehouse->fill($data);
            $warehouse->save();

            return $warehouse->fresh(['locations']) ?? $warehouse->load('locations');
        });
    }

    /**
     * Delete a warehouse when no inventory records exist.
     *
     * @throws ValidationException
     */
    public function destroy(Warehouse $warehouse): void
    {
        if ($warehouse->inventories()->exists()) {
            throw ValidationException::withMessages([
                'warehouse' => 'Cannot delete a warehouse that has inventory records.',
            ]);
        }

        DB::transaction(fn (): bool => $warehouse->delete());
    }

    /**
     * List locations for a warehouse.
     *
     * @param  array{
     *     search?: string|null,
     *     is_active?: bool|null,
     *     sort?: string|null,
     *     per_page?: int|null
     * }  $params
     * @return LengthAwarePaginator<int, WarehouseLocation>
     */
    public function listLocations(Warehouse $warehouse, array $params = []): LengthAwarePaginator
    {
        $params['warehouse_id'] = $warehouse->id;

        return WarehouseLocation::query()
            ->filter($params)
            ->applySort($params['sort'] ?? null)
            ->paginate($this->perPage($params));
    }

    /**
     * Create a location within a warehouse.
     *
     * @param  array{
     *     name: string,
     *     code?: string|null,
     *     aisle?: string|null,
     *     rack?: string|null,
     *     shelf?: string|null,
     *     bin?: string|null,
     *     is_active?: bool,
     *     sort_order?: int
     * }  $data
     */
    public function storeLocation(Warehouse $warehouse, array $data): WarehouseLocation
    {
        return $warehouse->locations()->create([
            'name' => $data['name'],
            'code' => $data['code'] ?? null,
            'aisle' => $data['aisle'] ?? null,
            'rack' => $data['rack'] ?? null,
            'shelf' => $data['shelf'] ?? null,
            'bin' => $data['bin'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);
    }

    /**
     * Update a warehouse location.
     *
     * @param  array{
     *     name?: string,
     *     code?: string|null,
     *     aisle?: string|null,
     *     rack?: string|null,
     *     shelf?: string|null,
     *     bin?: string|null,
     *     is_active?: bool,
     *     sort_order?: int
     * }  $data
     */
    public function updateLocation(WarehouseLocation $location, array $data): WarehouseLocation
    {
        $location->fill($data);
        $location->save();

        return $location->fresh() ?? $location;
    }

    /**
     * Delete a warehouse location when no inventory records exist.
     *
     * @throws ValidationException
     */
    public function destroyLocation(WarehouseLocation $location): void
    {
        if ($location->inventories()->exists()) {
            throw ValidationException::withMessages([
                'location' => 'Cannot delete a location that has inventory records.',
            ]);
        }

        DB::transaction(fn (): bool => $location->delete());
    }

    /**
     * @param  array{per_page?: int|null}  $params
     */
    protected function perPage(array $params): int
    {
        return max(1, min((int) ($params['per_page'] ?? 15), 100));
    }
}
