<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Driver\Auth;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Validates driver change-password payloads.
 */
class ChangePasswordRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ];
    }
}
