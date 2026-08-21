<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Product;

use App\Enums\Media\MediaCollection;
use App\Http\Resources\Media\MediaResource;
use App\Http\Resources\Tenant\Inventory\InventoryWarehouseStockResource;
use App\Http\Resources\Tenant\Unit\UnitResource;
use App\Models\Tenant\Inventory;
use App\Models\Tenant\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * API resource for tenant product variants.
 *
 * @mixin ProductVariant
 */
class ProductVariantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ProductVariant $variant */
        $variant = $this->resource;
        $image = $variant->getFirstMedia(MediaCollection::Images->value);
        $warehouseStocks = $this->warehouseStocks($variant);

        return [
            'id' => $variant->id,
            'product_id' => $variant->product_id,
            'name' => $variant->name,
            'sku' => $variant->sku,
            'barcode' => $variant->barcode,
            'unit_id' => $variant->unit_id,
            'is_active' => (bool) $variant->is_active,
            'weight' => $variant->weight,
            'length' => $variant->length,
            'width' => $variant->width,
            'height' => $variant->height,
            'sort_order' => $variant->sort_order,
            'unit' => $this->whenLoaded('unit', fn () => new UnitResource($variant->unit)),
            'option_values' => $this->whenLoaded('optionValues'),
            'prices' => ProductPriceResource::collection($this->whenLoaded('prices')),
            'inventory' => InventoryWarehouseStockResource::collection($this->whenLoaded('inventories')),
            'total_quantity' => $this->when(
                $warehouseStocks !== null,
                fn () => (int) ($warehouseStocks?->sum('quantity') ?? 0),
            ),
            'total_reserved_quantity' => $this->when(
                $warehouseStocks !== null,
                fn () => (int) ($warehouseStocks?->sum('reserved_quantity') ?? 0),
            ),
            'total_available_quantity' => $this->when(
                $warehouseStocks !== null,
                fn () => (int) ($warehouseStocks?->sum(fn (Inventory $inventory): int => $inventory->availableQuantity()) ?? 0),
            ),
            'image' => $image ? new MediaResource($image) : null,
            'media' => MediaResource::collection(
                $variant->relationLoaded('media')
                    ? $variant->getMedia(MediaCollection::Images->value)
                    : collect(),
            ),
            'created_at' => $variant->created_at,
            'updated_at' => $variant->updated_at,
        ];
    }

    /**
     * @return Collection<int, Inventory>|null
     */
    protected function warehouseStocks(ProductVariant $variant): ?Collection
    {
        if (! $variant->relationLoaded('inventories')) {
            return null;
        }

        return $variant->inventories;
    }
}
