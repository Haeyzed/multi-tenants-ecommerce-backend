<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\HR;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class StoreTaxTableRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'country_code' => ['required', 'string', 'size:2'],
            'name' => ['required', 'string', 'max:255'],
            'year' => [
                'required',
                'integer',
                'min:2000',
                'max:2100',
                Rule::unique('tax_tables', 'year')->where(
                    fn ($query) => $query->where('country_code', strtoupper((string) $this->input('country_code'))),
                ),
            ],
            'currency' => ['sometimes', 'string', 'size:3'],
            'is_active' => ['sometimes', 'boolean'],
            'relief_percent' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'relief_fixed' => ['sometimes', 'numeric', 'min:0'],
            'relief_minimum_percent' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'personal_allowance' => ['sometimes', 'numeric', 'min:0'],
            'bands' => ['required', 'array', 'min:1'],
            'bands.*.min_amount' => ['required', 'numeric', 'min:0'],
            'bands.*.max_amount' => ['sometimes', 'nullable', 'numeric', 'gte:bands.*.min_amount'],
            'bands.*.rate_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'bands.*.sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
