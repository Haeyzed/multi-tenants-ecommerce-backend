<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Loyalty;

use App\Models\Tenant\LoyaltyProgram;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LoyaltyProgram
 */
class LoyaltyProgramResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var LoyaltyProgram $program */
        $program = $this->resource;

        return [
            'id' => $program->id,
            'name' => $program->name,
            'is_active' => $program->is_active,
            'points_per_currency_unit' => $program->points_per_currency_unit,
            'redemption_points_per_currency' => $program->redemption_points_per_currency,
            'min_redemption_points' => $program->min_redemption_points,
            'max_redemption_percent' => $program->max_redemption_percent,
            'earn_on_order_paid' => $program->earn_on_order_paid,
            'created_at' => $program->created_at,
            'updated_at' => $program->updated_at,
        ];
    }
}
