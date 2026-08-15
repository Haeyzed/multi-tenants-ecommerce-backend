<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Unit;

use App\Models\Tenant\Unit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API resource for tenant measurement units.
 *
 * @mixin Unit
 */
class UnitResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Unit $unit */
        $unit = $this->resource;

        return [
            'id' => $unit->id,
            'name' => $unit->name,
            'short_name' => $unit->short_name,
            'code' => $unit->code,
            'is_active' => (bool) $unit->is_active,
            'sort_order' => $unit->sort_order,
            'created_at' => $unit->created_at,
            'updated_at' => $unit->updated_at,
        ];
    }
}
