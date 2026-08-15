<?php

declare(strict_types=1);

namespace App\Http\Resources\Landlord\World;

use App\Models\Landlord\World\Timezone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API resource for Timezone world data.
 *
 * @mixin Timezone
 */
class TimezoneResource extends JsonResource
{
    /**
     * Transform the timezone resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => data_get($this->resource, 'id'),
            'country_id' => data_get($this->resource, 'country_id'),
            'name' => data_get($this->resource, 'name'),
        ];
    }
}
