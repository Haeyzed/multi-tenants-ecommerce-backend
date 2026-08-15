<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Tax;

use App\Models\Tenant\TaxZone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TaxZone
 */
class TaxZoneResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var TaxZone $zone */
        $zone = $this->resource;

        return [
            'id' => $zone->id,
            'name' => $zone->name,
            'is_active' => (bool) $zone->is_active,
            'locations' => TaxZoneLocationResource::collection($this->whenLoaded('locations')),
            'rules' => TaxRuleResource::collection($this->whenLoaded('rules')),
            'created_at' => $zone->created_at,
            'updated_at' => $zone->updated_at,
        ];
    }
}
