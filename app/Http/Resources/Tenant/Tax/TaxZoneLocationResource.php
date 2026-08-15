<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Tax;

use App\Models\Tenant\TaxZoneLocation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TaxZoneLocation
 */
class TaxZoneLocationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var TaxZoneLocation $location */
        $location = $this->resource;

        return [
            'id' => $location->id,
            'country_id' => $location->country_id,
            'state_id' => $location->state_id,
            'city_id' => $location->city_id,
        ];
    }
}
