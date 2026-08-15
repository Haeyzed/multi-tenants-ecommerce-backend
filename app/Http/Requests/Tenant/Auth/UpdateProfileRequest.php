<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Auth;

use App\Http\Requests\BaseRequest;
use App\Support\Media\MediaValidation;

/**
 * Validates tenant profile update payloads.
 */
class UpdateProfileRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['sometimes', 'required', 'string', 'max:255'],
            'last_name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'avatar' => MediaValidation::image(required: false),
        ];
    }
}
