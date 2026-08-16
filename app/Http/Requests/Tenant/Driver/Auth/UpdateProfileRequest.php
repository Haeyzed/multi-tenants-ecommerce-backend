<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Driver\Auth;

use App\Enums\Tenant\Driver\DriverAvailability;
use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

/**
 * Validates driver profile update payloads.
 */
class UpdateProfileRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var int|string|null $driverId */
        $driverId = $this->user()?->getAuthIdentifier();

        return [
            'first_name' => ['sometimes', 'required', 'string', 'max:255'],
            'last_name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('drivers', 'email')->ignore($driverId)->withoutTrashed(),
            ],
            'phone' => [
                'sometimes',
                'nullable',
                'string',
                'max:50',
                Rule::unique('drivers', 'phone')->ignore($driverId)->withoutTrashed(),
            ],
            'availability' => ['sometimes', 'required', 'string', Rule::enum(DriverAvailability::class)],
        ];
    }
}
