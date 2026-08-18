<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\HR;

use App\Http\Requests\BaseRequest;

class UpdateInterviewMeetingRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'meeting_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'meeting_password' => ['sometimes', 'nullable', 'string', 'max:64'],
        ];
    }
}
