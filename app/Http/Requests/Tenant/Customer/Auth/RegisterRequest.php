<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Customer\Auth;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Validates customer registration payloads.
 */
class RegisterRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('customers', 'email')->withoutTrashed()],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50', Rule::unique('customers', 'phone')->withoutTrashed()],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ];
    }
}
