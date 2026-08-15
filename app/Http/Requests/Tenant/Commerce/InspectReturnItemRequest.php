<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Commerce;

use App\Enums\Tenant\Commerce\ReturnInspectionStatus;
use App\Enums\Tenant\Commerce\ReturnItemCondition;
use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class InspectReturnItemRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'inspection_status' => ['required', 'string', Rule::enum(ReturnInspectionStatus::class)],
            'condition' => ['sometimes', 'nullable', 'string', Rule::enum(ReturnItemCondition::class)],
            'inspection_note' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'restock' => ['sometimes', 'boolean'],
        ];
    }
}
