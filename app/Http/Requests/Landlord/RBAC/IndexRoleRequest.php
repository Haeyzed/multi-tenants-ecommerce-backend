<?php

declare(strict_types=1);

namespace App\Http\Requests\Landlord\RBAC;

use App\Http\Requests\BaseRequest;

/**
 * Validates query parameters for listing landlord roles.
 */
class IndexRoleRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
