<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Inventory;

use App\Http\Resources\Tenant\Product\ProductResource;
use App\Http\Resources\Tenant\Product\ProductVariantResource;
use App\Http\Resources\Tenant\Warehouse\WarehouseLocationResource;
use App\Http\Resources\Tenant\Warehouse\WarehouseResource;
use App\Models\Tenant\Inventory;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API resource for tenant inventory records.
 *
 * @mixin Inventory
 */
class InventoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Inventory $inventory */
        $inventory = $this->resource;

        return [
            'id' => $inventory->id,
            'warehouse_id' => $inventory->warehouse_id,
            'warehouse_location_id' => $inventory->warehouse_location_id,
            'inventoryable_type' => $inventory->inventoryable_type,
            'inventoryable_id' => $inventory->inventoryable_id,
            'quantity' => $inventory->quantity,
            'reserved_quantity' => $inventory->reserved_quantity,
            'available_quantity' => $inventory->availableQuantity(),
            'reorder_level' => $inventory->reorder_level,
            'reorder_quantity' => $inventory->reorder_quantity,
            'warehouse' => $this->whenLoaded('warehouse', fn () => new WarehouseResource($inventory->warehouse)),
            'warehouse_location' => $this->whenLoaded(
                'warehouseLocation',
                fn () => new WarehouseLocationResource($inventory->warehouseLocation),
            ),
            'inventoryable' => $this->whenLoaded('inventoryable', function () use ($inventory) {
                $inventoryable = $inventory->inventoryable;

                if ($inventoryable instanceof Product) {
                    return new ProductResource($inventoryable);
                }

                if ($inventoryable instanceof ProductVariant) {
                    return new ProductVariantResource($inventoryable);
                }

                return $inventoryable;
            }),
            'movements' => InventoryMovementResource::collection($this->whenLoaded('movements')),
            'created_at' => $inventory->created_at,
            'updated_at' => $inventory->updated_at,
        ];
    }
}
