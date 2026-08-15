<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Inventory;

use App\Http\Requests\BaseRequest;

/**
 * Validates inventory release payloads.
 */
class ReleaseInventoryRequest extends BaseRequest
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
