<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\HR;

use App\Http\Requests\BaseRequest;
use App\Models\HR\Department;
use Illuminate\Validation\Rule;

class UpdateDepartmentRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Department $department */
        $department = $this->route('department');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['sometimes', 'nullable', 'string', 'max:50', Rule::unique('departments', 'code')->ignore($department->id)],
            'description' => ['sometimes', 'nullable', 'string'],
            'manager_id' => ['sometimes', 'nullable', 'integer', 'exists:employees,id'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
