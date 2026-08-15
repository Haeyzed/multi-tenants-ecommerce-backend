<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Loyalty;

use App\Http\Requests\BaseRequest;

/**
 * Validates a manual staff point adjustment.
 */
class StoreLoyaltyAdjustmentRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'points' => ['required', 'integer', 'not_in:0', 'min:-1000000', 'max:1000000'],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
