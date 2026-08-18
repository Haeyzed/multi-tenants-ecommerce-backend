<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\HR;

use App\Http\Requests\BaseRequest;

class UpdateInterviewMeetingProviderSettingRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('hr.settings.update') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'enabled' => ['sometimes', 'boolean'],
            'credentials' => ['sometimes', 'array'],
            'credentials.*' => ['nullable', 'string', 'max:2048'],
        ];
    }
}
