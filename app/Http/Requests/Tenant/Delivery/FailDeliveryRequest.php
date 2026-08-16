<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Delivery;

use App\Http\Requests\BaseRequest;

/**
 * Validates mark-failed payloads.
 */
class FailDeliveryRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'failure_reason' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
