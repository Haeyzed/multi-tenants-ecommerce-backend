<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\HR;

use App\Models\Tenant\PublicHoliday;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PublicHoliday
 */
class PublicHolidayResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var PublicHoliday $holiday */
        $holiday = $this->resource;

        return [
            'id' => $holiday->id,
            'observed_on' => $holiday->observed_on?->toDateString(),
            'name' => $holiday->name,
            'repeats_annually' => $holiday->repeats_annually,
            'created_at' => $holiday->created_at,
            'updated_at' => $holiday->updated_at,
        ];
    }
}
