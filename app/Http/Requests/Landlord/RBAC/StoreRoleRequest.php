<?php

declare(strict_types=1);

namespace App\Http\Requests\Landlord\RBAC;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

/**
 * Validates landlord role creation payloads.
 */
class StoreRoleRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles', 'name')->where('guard_name', 'landlord'),
            ],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')->where('guard_name', 'landlord')],
        ];
    }
}
