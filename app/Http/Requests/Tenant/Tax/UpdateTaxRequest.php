<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Tax;

use App\Http\Requests\BaseRequest;
use App\Models\Tenant\Tax;
use Illuminate\Validation\Rule;

class UpdateTaxRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Tax|null $tax */
        $tax = $this->route('tax');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['sometimes', 'string', 'max:50', Rule::unique('taxes', 'code')->ignore($tax?->id)],
            'is_active' => ['sometimes', 'boolean'],
            'is_inclusive' => ['sometimes', 'boolean'],
            'priority' => ['sometimes', 'integer', 'min:0'],
            'rates' => ['sometimes', 'array'],
            'rates.*.rate' => ['required_with:rates', 'numeric', 'min:0'],
            'rates.*.effective_from' => ['sometimes', 'nullable', 'date'],
            'rates.*.effective_to' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
