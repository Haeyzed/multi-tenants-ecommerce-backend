<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Customer\Auth;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Validates customer password reset payloads.
 */
class ResetPasswordRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ];
    }
}
