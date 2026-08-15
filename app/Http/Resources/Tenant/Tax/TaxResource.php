<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Tax;

use App\Models\Tenant\Tax;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Tax
 */
class TaxResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Tax $tax */
        $tax = $this->resource;

        return [
            'id' => $tax->id,
            'name' => $tax->name,
            'code' => $tax->code,
            'is_active' => (bool) $tax->is_active,
            'is_inclusive' => (bool) $tax->is_inclusive,
            'priority' => $tax->priority,
            'rates' => TaxRateResource::collection($this->whenLoaded('rates')),
            'rules' => TaxRuleResource::collection($this->whenLoaded('rules')),
            'created_at' => $tax->created_at,
            'updated_at' => $tax->updated_at,
        ];
    }
}
