<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\HR;

use App\Enums\Tenant\HR\ApplicationSource;
use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class PublicApplyJobRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'cover_letter' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'source' => ['sometimes', 'nullable', 'string', Rule::enum(ApplicationSource::class)],
            'portfolio_url' => ['sometimes', 'nullable', 'url', 'max:255'],
            'linkedin_url' => ['sometimes', 'nullable', 'url', 'max:255'],
            'resume' => [
                'sometimes',
                'file',
                'max:'.(int) config('media.upload_limits.document', 20480),
                'mimetypes:'.implode(',', config('media.mimes.document', [])),
            ],
        ];
    }
}
