<?php

declare(strict_types=1);

namespace App\Http\Requests\Landlord\Notification;

use App\Enums\Notification\DeviceType;
use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

/**
 * Validates device token registration.
 */
class StoreDeviceTokenRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'device_type' => ['required', 'string', Rule::enum(DeviceType::class)],
            'device_token' => ['required', 'string', 'max:512'],
            'provider' => ['sometimes', 'nullable', 'string', 'max:50'],
            'app_version' => ['sometimes', 'nullable', 'string', 'max:50'],
        ];
    }
}
