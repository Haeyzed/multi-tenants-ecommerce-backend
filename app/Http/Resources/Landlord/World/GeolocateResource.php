<?php

declare(strict_types=1);

namespace App\Http\Resources\Landlord\World;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API resource for IP geolocation payload from the World package.
 */
class GeolocateResource extends JsonResource
{
    /**
     * Transform the geolocation resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'ip' => data_get($this->resource, 'ip'),
            'country' => data_get($this->resource, 'country'),
            'state' => data_get($this->resource, 'state'),
            'city' => data_get($this->resource, 'city'),
            'coordinates' => data_get($this->resource, 'coordinates'),
            'timezone' => data_get($this->resource, 'timezone'),
            'postal_code' => data_get($this->resource, 'postal_code'),
        ];
    }
}
