<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Commerce;

use App\Http\Requests\BaseRequest;

class UpdateGiftCardRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'expires_at' => ['sometimes', 'nullable', 'date'],
            'customer_id' => ['sometimes', 'nullable', 'integer', 'exists:customers,id'],
            'meta' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
