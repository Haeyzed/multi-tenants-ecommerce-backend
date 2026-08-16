<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Customer;

use App\Models\Tenant\CustomerGroup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API resource for tenant customer groups.
 *
 * @mixin CustomerGroup
 */
class CustomerGroupResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var CustomerGroup $group */
        $group = $this->resource;

        return [
            'id' => $group->id,
            'name' => $group->name,
            'slug' => $group->slug,
            'description' => $group->description,
            'is_active' => (bool) $group->is_active,
            'sort_order' => $group->sort_order,
            'customers_count' => $this->when(isset($group->customers_count), $group->customers_count),
            'created_at' => $group->created_at,
            'updated_at' => $group->updated_at,
        ];
    }
}
