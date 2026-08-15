<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Customer;

use App\Models\Tenant\CustomerSegment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API resource for rule-based customer segments.
 *
 * @mixin CustomerSegment
 */
class CustomerSegmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var CustomerSegment $segment */
        $segment = $this->resource;

        return [
            'id' => $segment->id,
            'name' => $segment->name,
            'slug' => $segment->slug,
            'description' => $segment->description,
            'match' => $segment->matchMode(),
            'conditions' => $segment->conditions(),
            'is_active' => (bool) $segment->is_active,
            'sort_order' => $segment->sort_order,
            'customers_count' => $segment->getAttribute('customers_count'),
            'created_at' => $segment->created_at,
            'updated_at' => $segment->updated_at,
        ];
    }
}
