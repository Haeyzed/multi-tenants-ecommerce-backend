<?php

declare(strict_types=1);

namespace App\Services\Tenant\Brand;

use App\Enums\Media\MediaCollection;
use App\Models\Tenant\Brand;
use App\Services\Media\MediaService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Tenant brand catalog operations.
 */
class BrandService
{
    public function __construct(private readonly MediaService $mediaService) {}

    /**
     * Paginate brands with search, filters, and sorts.
     *
     * @param  array{
     *     search?: string|null,
     *     is_active?: bool|null,
     *     sort?: string|null,
     *     per_page?: int|null
     * }  $params
     * @return LengthAwarePaginator<int, Brand>
     */
    public function list(array $params = []): LengthAwarePaginator
    {
        return Brand::query()
            ->with('media')
            ->filter($params)
            ->applySort($params['sort'] ?? null)
            ->paginate($this->perPage($params));
    }

    /**
     * Brand options for select inputs.
     *
     * @param  array{search?: string|null, is_active?: bool|null}  $params
     * @return Collection<int, array{label: string, value: int}>
     */
    public function options(array $params = []): Collection
    {
        return Brand::query()
            ->filter($params)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Brand $brand): array => [
                'label' => $brand->name,
                'value' => $brand->id,
            ])
            ->values();
    }

    /**
     * Create a brand and optionally attach a logo.
     *
     * @param  array{
     *     name: string,
     *     description?: string|null,
     *     is_active?: bool,
     *     sort_order?: int
     * }  $data
     */
    public function store(array $data, ?UploadedFile $logo = null): Brand
    {
        return DB::transaction(function () use ($data, $logo): Brand {
            /** @var Brand $brand */
            $brand = Brand::query()->create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'sort_order' => $data['sort_order'] ?? 0,
            ]);

            if ($logo !== null) {
                $this->mediaService->replace($brand, $logo, MediaCollection::Logo);
            }

            return $brand->load('media');
        });
    }

    /**
     * Retrieve a brand with media loaded.
     */
    public function show(Brand $brand): Brand
    {
        return $brand->load('media');
    }

    /**
     * Update a brand and optionally replace its logo.
     *
     * @param  array{
     *     name?: string,
     *     description?: string|null,
     *     is_active?: bool,
     *     sort_order?: int
     * }  $data
     */
    public function update(Brand $brand, array $data, ?UploadedFile $logo = null): Brand
    {
        return DB::transaction(function () use ($brand, $data, $logo): Brand {
            $brand->fill($data);
            $brand->save();

            if ($logo !== null) {
                $this->mediaService->replace($brand, $logo, MediaCollection::Logo);
            }

            return $brand->fresh(['media']) ?? $brand->load('media');
        });
    }

    /**
     * Delete a brand when no products are associated.
     *
     * Product module is not implemented yet; once products exist,
     * deletion must be blocked while products reference the brand.
     *
     * @throws ValidationException
     */
    public function destroy(Brand $brand): void
    {
        if ($this->hasAssociatedProducts($brand)) {
            throw ValidationException::withMessages([
                'brand' => 'Cannot delete a brand that has associated products.',
            ]);
        }

        DB::transaction(function () use ($brand): void {
            $this->mediaService->removeCollection($brand, MediaCollection::Logo);
            $brand->delete();
        });
    }

    /**
     * Replace the brand logo.
     */
    public function storeLogo(Brand $brand, UploadedFile $logo): Brand
    {
        $this->mediaService->replace($brand, $logo, MediaCollection::Logo);

        return $brand->fresh(['media']) ?? $brand->load('media');
    }

    /**
     * Remove the brand logo.
     */
    public function destroyLogo(Brand $brand): Brand
    {
        $this->mediaService->removeCollection($brand, MediaCollection::Logo);

        return $brand->fresh(['media']) ?? $brand->load('media');
    }

    /**
     * Whether the brand has associated products.
     */
    protected function hasAssociatedProducts(Brand $brand): bool
    {
        return $brand->products()->exists();
    }

    /**
     * @param  array{per_page?: int|null}  $params
     */
    protected function perPage(array $params): int
    {
        return max(1, min((int) ($params['per_page'] ?? 15), 100));
    }
}
