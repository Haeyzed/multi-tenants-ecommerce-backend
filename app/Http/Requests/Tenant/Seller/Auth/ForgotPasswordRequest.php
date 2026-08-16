<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Seller\Auth;

use App\Http\Requests\BaseRequest;

/**
 * Validates seller forgot-password requests.
 */
class ForgotPasswordRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
        ];
    }
}
