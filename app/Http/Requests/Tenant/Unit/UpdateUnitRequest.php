<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Unit;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

/**
 * Validates unit update payloads.
 */
class UpdateUnitRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var int|string|null $unitId */
        $unitId = $this->route('unit');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'short_name' => ['sometimes', 'nullable', 'string', 'max:50'],
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('units', 'code')->ignore($unitId),
            ],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }
}
