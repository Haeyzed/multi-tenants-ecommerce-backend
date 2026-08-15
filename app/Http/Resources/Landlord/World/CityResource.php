<?php

declare(strict_types=1);

namespace App\Http\Resources\Landlord\World;

use App\Models\Landlord\World\City;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API resource for City world data.
 *
 * @mixin City
 */
class CityResource extends JsonResource
{
    /**
     * Transform the city resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => data_get($this->resource, 'id'),
            'name' => data_get($this->resource, 'name'),
            'country_id' => data_get($this->resource, 'country_id'),
            'state_id' => data_get($this->resource, 'state_id'),
            'country_code' => data_get($this->resource, 'country_code'),
            'state_code' => data_get($this->resource, 'state_code'),
            'latitude' => data_get($this->resource, 'latitude'),
            'longitude' => data_get($this->resource, 'longitude'),
        ];
    }
}
