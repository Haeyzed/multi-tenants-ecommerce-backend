<?php

declare(strict_types=1);

namespace App\Services\Tenant\Shipping;

use App\Models\Tenant\ShippingMethod;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * CRUD for tenant shipping methods.
 */
class ShippingMethodService
{
    /**
     * Retrieve a paginated list of resources.
     *
     * @param  array{search?: string|null, is_active?: bool|null, per_page?: int|null}  $params
     * @return LengthAwarePaginator<int, ShippingMethod>
     */
    public function list(array $params = []): LengthAwarePaginator
    {
        $query = ShippingMethod::query()->orderBy('sort_order')->orderBy('name');

        if (isset($params['is_active'])) {
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
     * Return options for select inputs.
     *
     * @param  array{is_active?: bool|null}  $params
     * @return Collection<int, array{label: string, value: int}>
     */
    public function options(array $params = []): Collection
    {
        $query = ShippingMethod::query()->orderBy('sort_order')->orderBy('name');

        if (isset($params['is_active'])) {
            $query->where('is_active', (bool) $params['is_active']);
        } else {
            $query->where('is_active', true);
        }

        return $query->get(['id', 'name', 'code', 'amount'])
            ->map(fn (ShippingMethod $method): array => [
                'label' => $method->name.' ('.$method->amount.')',
                'value' => $method->id,
            ])
            ->values();
    }

    /**
     * name: string, code: string, description?: string|null, amount: string|float, min_order_amount?: string|float|null, is_active?: bool, sort_order?: int, estimated_days_min?: int|null, estimated_days_max?: int|null }  $data
     *
     * @param  array{
     *     name: string,
     *     code: string,
     *     description?: string|null,
     *     amount: string|float,
     *     min_order_amount?: string|float|null,
     *     is_active?: bool,
     *     sort_order?: int,
     *     estimated_days_min?: int|null,
     *     estimated_days_max?: int|null
     * }  $data
     * @return ShippingMethod
     */
    public function store(array $data): ShippingMethod
    {
        return ShippingMethod::query()->create([
            'name' => $data['name'],
            'code' => $data['code'],
            'description' => $data['description'] ?? null,
            'amount' => $data['amount'],
            'min_order_amount' => $data['min_order_amount'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'sort_order' => $data['sort_order'] ?? 0,
            'estimated_days_min' => $data['estimated_days_min'] ?? null,
            'estimated_days_max' => $data['estimated_days_max'] ?? null,
        ]);
    }

    /**
     * Retrieve a single resource.
     *
     * @param  ShippingMethod  $method
     * @return ShippingMethod
     */
    public function show(ShippingMethod $method): ShippingMethod
    {
        return $method;
    }

    /**
     * name?: string, code?: string, description?: string|null, amount?: string|float, min_order_amount?: string|float|null, is_active?: bool, sort_order?: int, estimated_days_min?: int|null, estimated_days_max?: int|null }  $data
     *
     * @param  ShippingMethod  $method
     * @param  array{
     *     name?: string,
     *     code?: string,
     *     description?: string|null,
     *     amount?: string|float,
     *     min_order_amount?: string|float|null,
     *     is_active?: bool,
     *     sort_order?: int,
     *     estimated_days_min?: int|null,
     *     estimated_days_max?: int|null
     * }  $data
     * @return ShippingMethod
     */
    public function update(ShippingMethod $method, array $data): ShippingMethod
    {
        $method->fill($data);
        $method->save();

        return $method->fresh() ?? $method;
    }

    /**
     * Delete a resource.
     *
     * @param  ShippingMethod  $method
     * @return void
     *
     * @throws ValidationException
     */
    public function destroy(ShippingMethod $method): void
    {
        if ($method->orders()->exists() || $method->shipments()->exists()) {
            throw ValidationException::withMessages([
                'shipping_method' => 'Cannot delete a shipping method that is in use.',
            ]);
        }

        $method->delete();
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
