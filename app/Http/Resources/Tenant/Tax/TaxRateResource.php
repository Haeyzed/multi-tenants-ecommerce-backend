<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Tax;

use App\Models\Tenant\TaxRate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TaxRate
 */
class TaxRateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var TaxRate $rate */
        $rate = $this->resource;

        return [
            'id' => $rate->id,
            'rate' => $rate->rate,
            'effective_from' => $rate->effective_from,
            'effective_to' => $rate->effective_to,
        ];
    }
}
