<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Catalog;

use App\Models\Tenant\ProductSpecification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API resource for product specifications.
 *
 * @mixin ProductSpecification
 */
class ProductSpecificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ProductSpecification $spec */
        $spec = $this->resource;

        return [
            'id' => $spec->id,
            'product_id' => $spec->product_id,
            'group' => $spec->group,
            'name' => $spec->name,
            'value' => $spec->value,
            'sort_order' => $spec->sort_order,
            'created_at' => $spec->created_at,
            'updated_at' => $spec->updated_at,
        ];
    }
}
