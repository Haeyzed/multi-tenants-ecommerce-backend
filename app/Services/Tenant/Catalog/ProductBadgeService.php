<?php

declare(strict_types=1);

namespace App\Services\Tenant\Catalog;

use App\Enums\Media\MediaCollection;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductBadge;
use App\Services\Media\MediaService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Tenant product badge catalog operations.
 */
class ProductBadgeService
{
    /**
     * Create a new class instance.
     *
     * @param  MediaService  $mediaService
     */
    public function __construct(private readonly MediaService $mediaService) {}

    /**
     * Paginate badges with search and filters.
     *
     * @param  array{
     *     search?: string|null,
     *     is_active?: bool|null,
     *     sort?: string|null,
     *     per_page?: int|null
     * }  $params
     * @return LengthAwarePaginator<int, ProductBadge>
     */
    public function list(array $params = []): LengthAwarePaginator
    {
        return ProductBadge::query()
            ->with('media')
            ->withCount('products')
            ->filter($params)
            ->applySort($params['sort'] ?? null)
            ->paginate($this->perPage($params));
    }

    /**
     * Badge options for select inputs.
     *
     * @param  array{search?: string|null, is_active?: bool|null}  $params
     * @return Collection<int, array{label: string, value: int}>
     */
    public function options(array $params = []): Collection
    {
        return ProductBadge::query()
            ->filter($params)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (ProductBadge $badge): array => [
                'label' => $badge->name,
                'value' => $badge->id,
            ])
            ->values();
    }

    /**
     * Retrieve a badge with media.
     *
     * @param  ProductBadge  $badge
     * @return ProductBadge
     */
    public function show(ProductBadge $badge): ProductBadge
    {
        return $badge->load('media')->loadCount('products');
    }

    /**
     * Create a badge and optionally attach an image.
     *
     * @param  array{name: string, slug?: string|null, color?: string|null, is_active?: bool, sort_order?: int}  $data
     * @param  ?UploadedFile  $image
     * @return ProductBadge
     */
    public function store(array $data, ?UploadedFile $image = null): ProductBadge
    {
        return DB::transaction(function () use ($data, $image): ProductBadge {
            /** @var ProductBadge $badge */
            $badge = ProductBadge::query()->create([
                'name' => $data['name'],
                'slug' => $data['slug'] ?? null,
                'color' => $data['color'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'sort_order' => $data['sort_order'] ?? 0,
            ]);

            if ($image !== null) {
                $this->mediaService->replace($badge, $image, MediaCollection::Image);
            }

            return $this->show($badge);
        });
    }

    /**
     * Update a badge and optionally replace its image.
     *
     * @param  ProductBadge  $badge
     * @param  array{name?: string, slug?: string|null, color?: string|null, is_active?: bool, sort_order?: int}  $data
     * @param  ?UploadedFile  $image
     * @return ProductBadge
     */
    public function update(ProductBadge $badge, array $data, ?UploadedFile $image = null): ProductBadge
    {
        return DB::transaction(function () use ($badge, $data, $image): ProductBadge {
            $badge->fill($data);
            $badge->save();

            if ($image !== null) {
                $this->mediaService->replace($badge, $image, MediaCollection::Image);
            }

            return $this->show($badge->fresh() ?? $badge);
        });
    }

    /**
     * Delete a badge and its image.
     *
     * @param  ProductBadge  $badge
     * @return void
     */
    public function destroy(ProductBadge $badge): void
    {
        DB::transaction(function () use ($badge): void {
            $this->mediaService->removeCollection($badge, MediaCollection::Image);
            $badge->delete();
        });
    }

    /**
     * Replace-set badges on a product.
     *
     * @param  Product  $product
     * @param  list<array{badge_id: int, sort_order?: int}|int>  $items
     * @return Product
     *
     * @throws ValidationException
     */
    public function syncToProduct(Product $product, array $items): Product
    {
        $sync = [];

        foreach ($items as $index => $item) {
            if (is_int($item) || (is_string($item) && ctype_digit($item))) {
                $badgeId = (int) $item;
                $sync[$badgeId] = ['sort_order' => $index];

                continue;
            }

            /** @var array{badge_id: int, sort_order?: int} $item */
            $badgeId = (int) $item['badge_id'];
            $sync[$badgeId] = ['sort_order' => (int) ($item['sort_order'] ?? $index)];
        }

        $badgeIds = array_keys($sync);

        if (count($badgeIds) !== count(array_unique($badgeIds))) {
            throw ValidationException::withMessages([
                'badges' => 'Badge ids must be unique.',
            ]);
        }

        if ($badgeIds !== []) {
            $existingCount = ProductBadge::query()->whereIn('id', $badgeIds)->count();

            if ($existingCount !== count($badgeIds)) {
                throw ValidationException::withMessages([
                    'badges' => 'One or more badges do not exist.',
                ]);
            }
        }

        $product->badges()->sync($sync);

        return $product->load('badges.media');
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
