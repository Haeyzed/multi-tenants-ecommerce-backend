<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Driver;

use App\Enums\Tenant\Delivery\DeliveryStatus;
use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

/**
 * Validates staff driver delivery history filters.
 */
class IndexDriverDeliveriesRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'nullable', 'string', Rule::enum(DeliveryStatus::class)],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
