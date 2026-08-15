<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Loyalty;

use App\Http\Requests\BaseRequest;

/**
 * Validates loyalty program settings updates.
 */
class UpdateLoyaltyProgramRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'points_per_currency_unit' => ['sometimes', 'numeric', 'min:0', 'max:9999999999'],
            'redemption_points_per_currency' => ['sometimes', 'integer', 'min:1'],
            'min_redemption_points' => ['sometimes', 'integer', 'min:1'],
            'max_redemption_percent' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'earn_on_order_paid' => ['sometimes', 'boolean'],
        ];
    }
}
