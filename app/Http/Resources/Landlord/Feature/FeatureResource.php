<?php

declare(strict_types=1);

namespace App\Http\Resources\Landlord\Feature;

use App\Models\Landlord\Feature;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API resource for landlord features.
 *
 * @mixin Feature
 */
class FeatureResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Feature $feature */
        $feature = $this->resource;

        return [
            'id' => $feature->id,
            'name' => $feature->name,
            'slug' => $feature->slug,
            'description' => $feature->description,
            'is_active' => (bool) $feature->is_active,
            'is_enabled' => $this->when(
                $feature->pivot !== null,
                fn (): bool => (bool) $feature->pivot->is_enabled,
            ),
            'limit' => $this->when(
                $feature->pivot !== null,
                fn (): ?int => $feature->pivot->limit !== null ? (int) $feature->pivot->limit : null,
            ),
            'created_at' => $feature->created_at,
            'updated_at' => $feature->updated_at,
        ];
    }
}
