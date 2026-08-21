<?php

declare(strict_types=1);

namespace App\Services\Tenant\Catalog;

use App\Models\Tenant\ProductAttribute;
use App\Models\Tenant\ProductAttributeValue;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Tenant-global product attribute and value CRUD.
 */
class ProductAttributeService
{
    /**
     * Paginate attributes with values.
     *
     * @param  array{search?: string|null, sort?: string|null, per_page?: int|null}  $params
     * @return LengthAwarePaginator<int, ProductAttribute>
     */
    public function list(array $params = []): LengthAwarePaginator
    {
        return ProductAttribute::query()
            ->with('values')
            ->filter($params)
            ->applySort($params['sort'] ?? null)
            ->paginate($this->perPage($params));
    }

    /**
     * Attribute options for select inputs.
     *
     * @param  array{search?: string|null}  $params
     * @return Collection<int, array{label: string, value: int}>
     */
    public function options(array $params = []): Collection
    {
        return ProductAttribute::query()
            ->filter($params)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (ProductAttribute $attribute): array => [
                'label' => $attribute->name,
                'value' => $attribute->id,
            ])
            ->values();
    }

    /**
     * Retrieve an attribute with values.
     *
     * @param  ProductAttribute  $attribute
     * @return ProductAttribute
     */
    public function show(ProductAttribute $attribute): ProductAttribute
    {
        return $attribute->load('values');
    }

    /**
     * Create an attribute.
     *
     * @param  array{name: string, slug?: string|null, sort_order?: int}  $data
     * @return ProductAttribute
     */
    public function store(array $data): ProductAttribute
    {
        /** @var ProductAttribute $attribute */
        $attribute = ProductAttribute::query()->create([
            'name' => $data['name'],
            'slug' => $data['slug'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        return $this->show($attribute);
    }

    /**
     * Update an attribute.
     *
     * @param  ProductAttribute  $attribute
     * @param  array{name?: string, slug?: string|null, sort_order?: int}  $data
     * @return ProductAttribute
     */
    public function update(ProductAttribute $attribute, array $data): ProductAttribute
    {
        $attribute->fill($data);
        $attribute->save();

        return $this->show($attribute->fresh() ?? $attribute);
    }

    /**
     * Delete an attribute and its values.
     *
     * @param  ProductAttribute  $attribute
     * @return void
     */
    public function destroy(ProductAttribute $attribute): void
    {
        DB::transaction(function () use ($attribute): void {
            $attribute->values()->delete();
            $attribute->delete();
        });
    }

    /**
     * Create a value under an attribute.
     *
     * @param  ProductAttribute  $attribute
     * @param  array{value: string, sort_order?: int}  $data
     * @return ProductAttributeValue
     */
    public function storeValue(ProductAttribute $attribute, array $data): ProductAttributeValue
    {
        /** @var ProductAttributeValue $value */
        $value = $attribute->values()->create([
            'value' => $data['value'],
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        return $value;
    }

    /**
     * Update an attribute value scoped to its parent attribute.
     *
     * @param  ProductAttribute  $attribute
     * @param  ProductAttributeValue  $value
     * @param  array{value?: string, sort_order?: int}  $data
     * @return ProductAttributeValue
     *
     * @throws ValidationException
     */
    public function updateValue(ProductAttribute $attribute, ProductAttributeValue $value, array $data): ProductAttributeValue
    {
        $this->assertValueBelongs($attribute, $value);

        $value->fill($data);
        $value->save();

        return $value->fresh() ?? $value;
    }

    /**
     * Delete an attribute value scoped to its parent attribute.
     *
     * @param  ProductAttribute  $attribute
     * @param  ProductAttributeValue  $value
     * @return void
     *
     * @throws ValidationException
     */
    public function destroyValue(ProductAttribute $attribute, ProductAttributeValue $value): void
    {
        $this->assertValueBelongs($attribute, $value);
        $value->delete();
    }

    /**
     * Assert value belongs.
     *
     * @param  ProductAttribute  $attribute
     * @param  ProductAttributeValue  $value
     * @return void
     *
     * @throws ValidationException
     */
    protected function assertValueBelongs(ProductAttribute $attribute, ProductAttributeValue $value): void
    {
        if ((int) $value->product_attribute_id !== (int) $attribute->id) {
            throw ValidationException::withMessages([
                'value' => 'Attribute value does not belong to this attribute.',
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
