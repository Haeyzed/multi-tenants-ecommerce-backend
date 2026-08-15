<?php

declare(strict_types=1);

namespace App\Http\Requests\Landlord\Notification;

use App\Http\Requests\BaseRequest;

/**
 * Validates notification template preview payloads.
 */
class PreviewNotificationTemplateRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'data' => ['sometimes', 'array'],
        ];
    }
}
