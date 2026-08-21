<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Pos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Pos\PosCatalogSearchRequest;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductVariant;
use App\Services\Tenant\Pos\PosCatalogService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

/**
 * POS catalog search and barcode lookup.
 */
class PosCatalogController extends Controller
{
    /**
     * Create a new class instance.
     *
     * @param  PosCatalogService  $catalog
     */
    public function __construct(private readonly PosCatalogService $catalog) {}

    /**
     * Search.
     *
     * @param  PosCatalogSearchRequest  $request
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Catalog search results.',
        type: 'array{success: true, message: string, data: array, meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}',
    )]
    public function search(PosCatalogSearchRequest $request): JsonResponse
    {
        abort_unless($request->user()?->can('pos.view') || $request->user()?->can('pos.sell'), 403);

        $results = $this->catalog->search($request->validated());

        return $this->success(
            collect($results->items())->map(fn ($item) => $this->mapCatalogItem($item))->all(),
            'POS catalog results retrieved successfully.',
            $this->paginationMeta($results),
        );
    }

    /**
     * Barcode.
     *
     * @param  PosCatalogSearchRequest  $request
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Barcode lookup result.',
        type: 'array{success: true, message: string, data: array, meta: null, errors: null}',
    )]
    public function barcode(PosCatalogSearchRequest $request): JsonResponse
    {
        abort_unless($request->user()?->can('pos.view') || $request->user()?->can('pos.sell'), 403);

        $barcode = (string) ($request->validated('q') ?? $request->query('barcode', ''));
        $item = $this->catalog->findByBarcode($barcode);

        return $this->success(
            $this->mapCatalogItem($item),
            'Barcode match retrieved successfully.',
        );
    }

    /**
     * Map catalog item.
     *
     * @param  ProductVariant|Product  $item
     * @return array<string, mixed>
     */
    protected function mapCatalogItem(ProductVariant|Product $item): array
    {
        if ($item instanceof ProductVariant) {
            return [
                'type' => 'variant',
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_variant_id' => $item->id,
                'name' => $item->name,
                'sku' => $item->sku,
                'barcode' => $item->barcode,
                'product_name' => $item->product?->name,
            ];
        }

        return [
            'type' => 'product',
            'id' => $item->id,
            'product_id' => $item->id,
            'product_variant_id' => null,
            'name' => $item->name,
            'sku' => null,
            'barcode' => null,
            'product_name' => $item->name,
        ];
    }
}
