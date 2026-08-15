<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Procurement;

use App\Enums\Tenant\Procurement\SupplierStatus;
use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class UpdateSupplierRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $supplierId = $this->route('supplier')?->id;

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['sometimes', 'string', 'max:50', Rule::unique('suppliers', 'code')->ignore($supplierId)],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'website' => ['sometimes', 'nullable', 'string', 'max:255'],
            'tax_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'status' => ['sometimes', 'string', Rule::enum(SupplierStatus::class)],
            'address_line_1' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address_line_2' => ['sometimes', 'nullable', 'string', 'max:255'],
            'country_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'state_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'city_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'postal_code' => ['sometimes', 'nullable', 'string', 'max:50'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
