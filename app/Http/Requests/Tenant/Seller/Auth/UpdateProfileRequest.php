<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Seller\Auth;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

/**
 * Validates seller self-service profile update payloads.
 */
class UpdateProfileRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var int|string|null $sellerId */
        $sellerId = $this->user()?->getAuthIdentifier();

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'email' => [
                'sometimes',
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('sellers', 'email')->ignore($sellerId)->withoutTrashed(),
            ],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
        ];
    }
}
