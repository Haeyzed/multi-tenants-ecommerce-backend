<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\HR;

use App\Enums\Tenant\HR\PerformanceCycleStatus;
use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class UpdatePerformanceCycleRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'starts_on' => ['sometimes', 'date'],
            'ends_on' => ['sometimes', 'date'],
            'status' => ['sometimes', 'string', Rule::enum(PerformanceCycleStatus::class)],
            'description' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
