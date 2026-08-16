<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Driver;

use App\Enums\Tenant\Driver\DriverAvailability;
use App\Enums\Tenant\Driver\DriverStatus;
use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Validates staff driver update payloads.
 */
class UpdateDriverRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var int|string|null $driverId */
        $driverId = $this->route('driver')?->getKey() ?? $this->route('driver');

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
            'password' => ['sometimes', 'nullable', 'string', Password::defaults()],
            'status' => ['sometimes', 'string', Rule::enum(DriverStatus::class)],
            'availability' => ['sometimes', 'string', Rule::enum(DriverAvailability::class)],
        ];
    }
}
