<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Warehouse;

use App\Models\Tenant\WarehouseLocation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API resource for warehouse storage locations.
 *
 * @mixin WarehouseLocation
 */
class WarehouseLocationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var WarehouseLocation $location */
        $location = $this->resource;

        return [
            'id' => $location->id,
            'warehouse_id' => $location->warehouse_id,
            'name' => $location->name,
            'code' => $location->code,
            'aisle' => $location->aisle,
            'rack' => $location->rack,
            'shelf' => $location->shelf,
            'bin' => $location->bin,
            'is_active' => (bool) $location->is_active,
            'sort_order' => $location->sort_order,
            'created_at' => $location->created_at,
            'updated_at' => $location->updated_at,
        ];
    }
}
