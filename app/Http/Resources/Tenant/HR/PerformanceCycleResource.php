<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\HR;

use App\Models\HR\PerformanceCycle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PerformanceCycle
 */
class PerformanceCycleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var PerformanceCycle $cycle */
        $cycle = $this->resource;

        return [
            'id' => $cycle->id,
            'name' => $cycle->name,
            'starts_on' => $cycle->starts_on?->toDateString(),
            'ends_on' => $cycle->ends_on?->toDateString(),
            'status' => $cycle->status,
            'description' => $cycle->description,
            'reviews_count' => $cycle->reviews_count ?? null,
            'created_at' => $cycle->created_at,
            'updated_at' => $cycle->updated_at,
        ];
    }
}
