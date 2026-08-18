<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\HR;

use App\Http\Requests\BaseRequest;

class StoreJobOfferRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'job_application_id' => ['required', 'integer', 'exists:job_applications,id'],
            'position' => ['sometimes', 'nullable', 'string', 'max:255'],
            'salary' => ['required', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'expires_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:today'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
