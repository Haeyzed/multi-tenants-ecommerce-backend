<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Loyalty;

use App\Http\Requests\BaseRequest;

/**
 * Validates a customer point redemption preview.
 */
class PreviewLoyaltyRedemptionRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'points' => ['required', 'integer', 'min:1'],
        ];
    }
}
