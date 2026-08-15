<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Inventory;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

/**
 * Validates inventory transfer payloads.
 */
class TransferInventoryRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'to_warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')],
            'quantity' => ['required', 'integer', 'min:1'],
        ];
    }
}
