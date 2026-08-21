<?php

declare(strict_types=1);

namespace App\Services\Tenant\Catalog;

use App\Models\Tenant\ProductOption;
use App\Models\Tenant\ProductOptionValue;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Tenant-global product option and value CRUD.
 */
class ProductOptionService
{
    /**
     * Paginate options with values.
     *
     * @param  array{search?: string|null, sort?: string|null, per_page?: int|null}  $params
     * @return LengthAwarePaginator<int, ProductOption>
     */
    public function list(array $params = []): LengthAwarePaginator
    {
        return ProductOption::query()
            ->with('values')
            ->filter($params)
            ->applySort($params['sort'] ?? null)
            ->paginate($this->perPage($params));
    }

    /**
     * Option options for select inputs.
     *
     * @param  array{search?: string|null}  $params
     * @return Collection<int, array{label: string, value: int}>
     */
    public function options(array $params = []): Collection
    {
        return ProductOption::query()
            ->filter($params)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (ProductOption $option): array => [
                'label' => $option->name,
                'value' => $option->id,
            ])
            ->values();
    }

    /**
     * Retrieve an option with values.
     *
     * @param  ProductOption  $option
     * @return ProductOption
     */
    public function show(ProductOption $option): ProductOption
    {
        return $option->load('values');
    }

    /**
     * Create an option.
     *
     * @param  array{name: string, slug?: string|null, sort_order?: int}  $data
     * @return ProductOption
     */
    public function store(array $data): ProductOption
    {
        /** @var ProductOption $option */
        $option = ProductOption::query()->create([
            'name' => $data['name'],
            'slug' => $data['slug'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        return $this->show($option);
    }

    /**
     * Update an option.
     *
     * @param  ProductOption  $option
     * @param  array{name?: string, slug?: string|null, sort_order?: int}  $data
     * @return ProductOption
     */
    public function update(ProductOption $option, array $data): ProductOption
    {
        $option->fill($data);
        $option->save();

        return $this->show($option->fresh() ?? $option);
    }

    /**
     * Delete an option and its values.
     *
     * @param  ProductOption  $option
     * @return void
     */
    public function destroy(ProductOption $option): void
    {
        DB::transaction(function () use ($option): void {
            $option->values()->delete();
            $option->delete();
        });
    }

    /**
     * Create a value under an option.
     *
     * @param  ProductOption  $option
     * @param  array{value: string, slug?: string|null, sort_order?: int}  $data
     * @return ProductOptionValue
     */
    public function storeValue(ProductOption $option, array $data): ProductOptionValue
    {
        /** @var ProductOptionValue $value */
        $value = $option->values()->create([
            'value' => $data['value'],
            'slug' => $data['slug'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        return $value;
    }

    /**
     * Update an option value scoped to its parent option.
     *
     * @param  ProductOption  $option
     * @param  ProductOptionValue  $value
     * @param  array{value?: string, slug?: string|null, sort_order?: int}  $data
     * @return ProductOptionValue
     *
     * @throws ValidationException
     */
    public function updateValue(ProductOption $option, ProductOptionValue $value, array $data): ProductOptionValue
    {
        $this->assertValueBelongs($option, $value);

        $value->fill($data);
        $value->save();

        return $value->fresh() ?? $value;
    }

    /**
     * Delete an option value scoped to its parent option.
     *
     * @param  ProductOption  $option
     * @param  ProductOptionValue  $value
     * @return void
     *
     * @throws ValidationException
     */
    public function destroyValue(ProductOption $option, ProductOptionValue $value): void
    {
        $this->assertValueBelongs($option, $value);
        $value->delete();
    }

    /**
     * Assert value belongs.
     *
     * @param  ProductOption  $option
     * @param  ProductOptionValue  $value
     * @return void
     *
     * @throws ValidationException
     */
    protected function assertValueBelongs(ProductOption $option, ProductOptionValue $value): void
    {
        if ((int) $value->product_option_id !== (int) $option->id) {
            throw ValidationException::withMessages([
                'value' => 'Option value does not belong to this option.',
            ]);
        }
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
