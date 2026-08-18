<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\HR;

use App\Http\Requests\BaseRequest;
use App\Models\Tenant\TaxTable;
use Illuminate\Validation\Rule;

class UpdateTaxTableRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var TaxTable $table */
        $table = $this->route('tax_table');

        return [
            'country_code' => ['sometimes', 'string', 'size:2'],
            'name' => ['sometimes', 'string', 'max:255'],
            'year' => [
                'sometimes',
                'integer',
                'min:2000',
                'max:2100',
                Rule::unique('tax_tables', 'year')
                    ->ignore($table->id)
                    ->where(fn ($query) => $query->where(
                        'country_code',
                        strtoupper((string) ($this->input('country_code') ?? $table->country_code)),
                    )),
            ],
            'currency' => ['sometimes', 'string', 'size:3'],
            'is_active' => ['sometimes', 'boolean'],
            'relief_percent' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'relief_fixed' => ['sometimes', 'numeric', 'min:0'],
            'relief_minimum_percent' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'personal_allowance' => ['sometimes', 'numeric', 'min:0'],
            'bands' => ['sometimes', 'array', 'min:1'],
            'bands.*.min_amount' => ['required_with:bands', 'numeric', 'min:0'],
            'bands.*.max_amount' => ['sometimes', 'nullable', 'numeric'],
            'bands.*.rate_percent' => ['required_with:bands', 'numeric', 'min:0', 'max:100'],
            'bands.*.sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
