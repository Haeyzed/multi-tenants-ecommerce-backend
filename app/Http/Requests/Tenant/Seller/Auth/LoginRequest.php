<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Seller\Auth;

use App\Http\Requests\BaseRequest;

/**
 * Validates seller login payloads.
 */
class LoginRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }
}
