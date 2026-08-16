<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Driver;

use App\Models\Tenant\DriverLocation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API resource for driver locations.
 *
 * @mixin DriverLocation
 */
class DriverLocationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var DriverLocation $location */
        $location = $this->resource;

        return [
            'id' => $location->id,
            'driver_id' => $location->driver_id,
            'delivery_id' => $location->delivery_id,
            'latitude' => $location->latitude,
            'longitude' => $location->longitude,
            'accuracy' => $location->accuracy,
            'heading' => $location->heading,
            'speed' => $location->speed,
            'recorded_at' => $location->recorded_at,
            'created_at' => $location->created_at,
            'updated_at' => $location->updated_at,
        ];
    }
}
