<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Inventory;

use App\Http\Resources\Tenant\Warehouse\WarehouseResource;
use App\Models\Tenant\Inventory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Warehouse-scoped stock row without nested catalogue records.
 *
 * @mixin Inventory
 */
class InventoryWarehouseStockResource extends JsonResource
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
            'quantity' => $inventory->quantity,
            'reserved_quantity' => $inventory->reserved_quantity,
            'available_quantity' => $inventory->availableQuantity(),
            'reorder_level' => $inventory->reorder_level,
            'reorder_quantity' => $inventory->reorder_quantity,
            'warehouse' => $this->whenLoaded('warehouse', fn () => new WarehouseResource($inventory->warehouse)),
        ];
    }
}
