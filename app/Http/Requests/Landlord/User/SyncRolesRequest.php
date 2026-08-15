<?php

declare(strict_types=1);

namespace App\Http\Requests\Landlord\User;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

/**
 * Validates landlord user role sync payloads.
 */
class SyncRolesRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'roles' => ['required', 'array'],
            'roles.*' => ['string', Rule::exists('roles', 'name')->where('guard_name', 'landlord')],
        ];
    }
}
