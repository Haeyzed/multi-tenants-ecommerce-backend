<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\User;

use App\Http\Requests\BaseRequest;
use App\Support\Media\MediaValidation;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Validates tenant user creation payloads.
 */
class StoreUserRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'password' => ['required', 'string', Password::defaults()],
            'seller_id' => ['sometimes', 'nullable', 'integer', 'exists:sellers,id'],
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['string', Rule::exists('roles', 'name')->where('guard_name', 'tenant')],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')->where('guard_name', 'tenant')],
            'avatar' => MediaValidation::image(required: false),
        ];
    }
}
