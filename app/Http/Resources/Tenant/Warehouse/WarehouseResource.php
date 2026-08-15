<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Warehouse;

use App\Models\Tenant\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API resource for tenant warehouses.
 *
 * @mixin Warehouse
 */
class WarehouseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Warehouse $warehouse */
        $warehouse = $this->resource;

        return [
            'id' => $warehouse->id,
            'name' => $warehouse->name,
            'code' => $warehouse->code,
            'description' => $warehouse->description,
            'address' => $warehouse->address,
            'country_id' => $warehouse->country_id,
            'state_id' => $warehouse->state_id,
            'city_id' => $warehouse->city_id,
            'phone' => $warehouse->phone,
            'email' => $warehouse->email,
            'is_active' => (bool) $warehouse->is_active,
            'is_default' => (bool) $warehouse->is_default,
            'sort_order' => $warehouse->sort_order,
            'locations' => WarehouseLocationResource::collection($this->whenLoaded('locations')),
            'created_at' => $warehouse->created_at,
            'updated_at' => $warehouse->updated_at,
        ];
    }
}
