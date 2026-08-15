<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\User;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

/**
 * Validates tenant user permission sync payloads.
 */
class SyncPermissionsRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'permissions' => ['required', 'array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')->where('guard_name', 'tenant')],
        ];
    }
}
