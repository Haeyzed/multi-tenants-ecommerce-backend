<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\HR;

use App\Http\Requests\BaseRequest;

class IndexRecruitmentActivityRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sort' => ['sometimes', 'nullable', 'string', 'max:64'],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
