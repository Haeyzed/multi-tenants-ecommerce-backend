<?php

declare(strict_types=1);

namespace App\Http\Resources\Landlord\World;

use App\Models\Landlord\World\Country;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API resource for Country world data.
 *
 * @mixin Country
 */
class CountryResource extends JsonResource
{
    /**
     * Transform the country resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => data_get($this->resource, 'id'),
            'iso2' => data_get($this->resource, 'iso2'),
            'iso3' => data_get($this->resource, 'iso3'),
            'name' => data_get($this->resource, 'name'),
            'phone_code' => data_get($this->resource, 'phone_code'),
            'region' => data_get($this->resource, 'region'),
            'subregion' => data_get($this->resource, 'subregion'),
            'native' => data_get($this->resource, 'native'),
            'latitude' => data_get($this->resource, 'latitude'),
            'longitude' => data_get($this->resource, 'longitude'),
            'emoji' => data_get($this->resource, 'emoji'),
            'emojiU' => data_get($this->resource, 'emojiU'),
        ];
    }
}
