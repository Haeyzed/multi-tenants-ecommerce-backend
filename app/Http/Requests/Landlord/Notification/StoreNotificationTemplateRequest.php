<?php

declare(strict_types=1);

namespace App\Http\Requests\Landlord\Notification;

use App\Enums\Notification\NotificationChannel;
use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

/**
 * Validates notification template creation payloads.
 */
class StoreNotificationTemplateRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'key' => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z0-9_.-]+$/', Rule::unique('notification_templates', 'key')],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => ['required', 'string', Rule::enum(NotificationChannel::class)],
            'variables' => ['sometimes', 'nullable', 'array'],
            'variables.*' => ['required', 'string', 'max:100'],
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'body' => ['sometimes', 'nullable', 'string'],
            'email_subject' => ['sometimes', 'nullable', 'string', 'max:255'],
            'email_body' => ['sometimes', 'nullable', 'string'],
            'push_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'push_body' => ['sometimes', 'nullable', 'string'],
            'sms_body' => ['sometimes', 'nullable', 'string'],
            'is_mandatory' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
