<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Tax;

use App\Enums\Tenant\Tax\TaxAppliesTo;
use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class StoreTaxRuleRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tax_id' => ['required', 'integer', Rule::exists('taxes', 'id')],
            'tax_zone_id' => ['required', 'integer', Rule::exists('tax_zones', 'id')],
            'applies_to' => ['required', Rule::enum(TaxAppliesTo::class)],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
