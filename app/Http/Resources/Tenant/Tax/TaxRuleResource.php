<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Tax;

use App\Models\Tenant\TaxRule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TaxRule
 */
class TaxRuleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var TaxRule $rule */
        $rule = $this->resource;

        return [
            'id' => $rule->id,
            'tax_id' => $rule->tax_id,
            'tax_zone_id' => $rule->tax_zone_id,
            'applies_to' => $rule->applies_to->value,
            'is_active' => (bool) $rule->is_active,
            'tax_zone' => new TaxZoneResource($this->whenLoaded('taxZone')),
        ];
    }
}
