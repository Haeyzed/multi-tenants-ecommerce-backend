<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Unit;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

/**
 * Validates unit creation payloads.
 */
class StoreUnitRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'short_name' => ['sometimes', 'nullable', 'string', 'max:50'],
            'code' => ['required', 'string', 'max:50', Rule::unique('units', 'code')],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }
}
