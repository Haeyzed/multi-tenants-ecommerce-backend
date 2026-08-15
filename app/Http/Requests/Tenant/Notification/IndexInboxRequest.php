<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Notification;

use App\Http\Requests\BaseRequest;

class IndexInboxRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
