<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Customer\Auth;

use App\Http\Requests\BaseRequest;

/**
 * Validates customer email verification token.
 */
class VerifyEmailRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
        ];
    }
}
