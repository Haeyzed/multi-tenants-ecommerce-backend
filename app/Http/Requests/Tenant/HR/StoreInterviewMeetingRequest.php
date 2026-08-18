<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\HR;

use App\Enums\Tenant\HR\MeetingProvider;
use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class StoreInterviewMeetingRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'meeting_provider' => ['sometimes', 'nullable', 'string', Rule::in(array_merge(MeetingProvider::publicValues(), ['fake']))],
            'meeting_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'meeting_password' => ['sometimes', 'nullable', 'string', 'max:64'],
            'recreate' => ['sometimes', 'boolean'],
        ];
    }
}
