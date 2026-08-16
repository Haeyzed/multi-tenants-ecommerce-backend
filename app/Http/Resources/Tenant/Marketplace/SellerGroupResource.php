<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Marketplace;

use App\Models\Tenant\SellerGroup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API resource for tenant seller groups.
 *
 * @mixin SellerGroup
 */
class SellerGroupResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var SellerGroup $group */
        $group = $this->resource;

        return [
            'id' => $group->id,
            'name' => $group->name,
            'slug' => $group->slug,
            'description' => $group->description,
            'commission_type' => $group->commission_type,
            'commission_rate' => $group->commission_rate,
            'commission_fixed_amount' => $group->commission_fixed_amount,
            'is_active' => (bool) $group->is_active,
            'sort_order' => $group->sort_order,
            'sellers_count' => $this->when(isset($group->sellers_count), $group->sellers_count),
            'created_at' => $group->created_at,
            'updated_at' => $group->updated_at,
        ];
    }
}
