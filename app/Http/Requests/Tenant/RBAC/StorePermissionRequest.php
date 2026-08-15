<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\RBAC;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

/**
 * Validates tenant permission creation payloads.
 */
class StorePermissionRequest extends BaseRequest
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
                Rule::unique('permissions', 'name')->where('guard_name', 'tenant'),
            ],
        ];
    }
}
