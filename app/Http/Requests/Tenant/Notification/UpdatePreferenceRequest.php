<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Notification;

use App\Enums\Notification\NotificationChannel;
use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class UpdatePreferenceRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'preferences' => ['required', 'array', 'min:1'],
            'preferences.*.notification_key' => ['required', 'string', 'max:255'],
            'preferences.*.channel' => ['required', 'string', Rule::enum(NotificationChannel::class)],
            'preferences.*.enabled' => ['required', 'boolean'],
        ];
    }
}
