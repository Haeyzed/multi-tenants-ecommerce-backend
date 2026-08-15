<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Inventory;

use App\Enums\Tenant\Catalog\InventoryMovementType;
use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

/**
 * Validates inventory adjustment payloads.
 */
class AdjustInventoryRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'quantity' => ['required', 'integer', 'not_in:0'],
            'type' => ['required', Rule::enum(InventoryMovementType::class)],
            'reason' => ['sometimes', 'nullable', 'string', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
