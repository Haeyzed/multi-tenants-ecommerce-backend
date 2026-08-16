<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Driver;

use App\Enums\Tenant\Driver\DriverAvailability;
use App\Enums\Tenant\Driver\DriverStatus;
use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Validates staff driver creation payloads.
 */
class StoreDriverRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('drivers', 'email')->withoutTrashed()],
            'phone' => ['nullable', 'string', 'max:50', Rule::unique('drivers', 'phone')->withoutTrashed()],
            'password' => ['required', 'string', Password::defaults()],
            'status' => ['sometimes', 'string', Rule::enum(DriverStatus::class)],
            'availability' => ['sometimes', 'string', Rule::enum(DriverAvailability::class)],
        ];
    }
}
