<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\HR;

use App\Enums\Tenant\HR\PerformanceCycleStatus;
use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class StorePerformanceCycleRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'status' => ['sometimes', 'string', Rule::enum(PerformanceCycleStatus::class)],
            'description' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
