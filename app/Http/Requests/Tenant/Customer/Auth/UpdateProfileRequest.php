<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Customer\Auth;

use App\Http\Requests\BaseRequest;
use App\Support\Media\MediaValidation;
use Illuminate\Validation\Rule;

/**
 * Validates customer profile update payloads.
 */
class UpdateProfileRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var int|string|null $customerId */
        $customerId = $this->user()?->getAuthIdentifier();

        return [
            'first_name' => ['sometimes', 'required', 'string', 'max:255'],
            'last_name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('customers', 'email')->ignore($customerId)->withoutTrashed(),
            ],
            'phone' => [
                'sometimes',
                'nullable',
                'string',
                'max:50',
                Rule::unique('customers', 'phone')->ignore($customerId)->withoutTrashed(),
            ],
            'avatar' => MediaValidation::image(required: false),
        ];
    }
}
