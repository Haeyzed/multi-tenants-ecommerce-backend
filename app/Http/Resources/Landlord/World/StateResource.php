<?php

declare(strict_types=1);

namespace App\Http\Resources\Landlord\World;

use App\Models\Landlord\World\State;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API resource for State world data.
 *
 * @mixin State
 */
class StateResource extends JsonResource
{
    /**
     * Transform the state resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => data_get($this->resource, 'id'),
            'name' => data_get($this->resource, 'name'),
            'country_id' => data_get($this->resource, 'country_id'),
            'country_code' => data_get($this->resource, 'country_code'),
            'state_code' => data_get($this->resource, 'state_code'),
            'type' => data_get($this->resource, 'type'),
            'latitude' => data_get($this->resource, 'latitude'),
            'longitude' => data_get($this->resource, 'longitude'),
        ];
    }
}
