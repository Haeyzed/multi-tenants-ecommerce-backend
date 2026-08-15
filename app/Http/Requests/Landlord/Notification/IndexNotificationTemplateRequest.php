<?php

declare(strict_types=1);

namespace App\Http\Requests\Landlord\Notification;

use App\Http\Requests\BaseRequest;

/**
 * Validates notification template index query params.
 */
class IndexNotificationTemplateRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'nullable', 'boolean'],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
