<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Commerce;

use App\Http\Requests\BaseRequest;

/**
 * Validates updating a cart line quantity.
 */
class UpdateCartItemRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'quantity' => ['required', 'integer', 'min:1'],
        ];
    }
}
