<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\HR;

use App\Models\HR\TaxTable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TaxTable
 */
class TaxTableResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var TaxTable $table */
        $table = $this->resource;

        return [
            'id' => $table->id,
            'country_code' => $table->country_code,
            'name' => $table->name,
            'year' => $table->year,
            'currency' => $table->currency,
            'is_active' => $table->is_active,
            'relief_percent' => $table->relief_percent,
            'relief_fixed' => $table->relief_fixed,
            'relief_minimum_percent' => $table->relief_minimum_percent,
            'personal_allowance' => $table->personal_allowance,
            'bands' => $this->whenLoaded('bands', fn () => $table->bands->map(fn ($band) => [
                'id' => $band->id,
                'sort_order' => $band->sort_order,
                'min_amount' => $band->min_amount,
                'max_amount' => $band->max_amount,
                'rate_percent' => $band->rate_percent,
            ])->values()),
            'created_at' => $table->created_at,
            'updated_at' => $table->updated_at,
        ];
    }
}
