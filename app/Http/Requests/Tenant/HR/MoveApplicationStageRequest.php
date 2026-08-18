<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\HR;

use App\Http\Requests\BaseRequest;

class MoveApplicationStageRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'recruitment_stage_id' => ['required', 'integer', 'exists:recruitment_stages,id'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
