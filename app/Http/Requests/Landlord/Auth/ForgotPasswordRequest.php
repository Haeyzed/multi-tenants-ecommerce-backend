<?php

declare(strict_types=1);

namespace App\Http\Requests\Landlord\Auth;

use App\Http\Requests\BaseRequest;

/**
 * Validates landlord forgot-password requests.
 */
class ForgotPasswordRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
        ];
    }
}
