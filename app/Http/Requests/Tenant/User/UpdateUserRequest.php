<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\User;

use App\Http\Requests\BaseRequest;
use App\Models\Tenant\User;
use App\Support\Media\MediaValidation;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Validates tenant user update payloads.
 */
class UpdateUserRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var User|null $user */
        $user = $this->route('user');

        return [
            'first_name' => ['sometimes', 'required', 'string', 'max:255'],
            'last_name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user?->id),
            ],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'password' => ['sometimes', 'nullable', 'string', Password::defaults()],
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['string', Rule::exists('roles', 'name')->where('guard_name', 'tenant')],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')->where('guard_name', 'tenant')],
            'avatar' => MediaValidation::image(required: false),
        ];
    }
}
