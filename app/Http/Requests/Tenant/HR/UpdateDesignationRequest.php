<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\HR;

use App\Http\Requests\BaseRequest;
use App\Models\Tenant\HR\Designation;
use Illuminate\Validation\Rule;

class UpdateDesignationRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Designation $designation */
        $designation = $this->route('designation');

        return [
            'department_id' => ['sometimes', 'nullable', 'integer', 'exists:departments,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['sometimes', 'nullable', 'string', 'max:50', Rule::unique('designations', 'code')->ignore($designation->id)],
            'description' => ['sometimes', 'nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
