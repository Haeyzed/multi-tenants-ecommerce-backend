<?php

declare(strict_types=1);

namespace App\Http\Requests\Landlord\World;

use App\Http\Requests\BaseRequest;

/**
 * Validates query parameters for listing currencies.
 */
class IndexCurrencyRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'filters' => ['sometimes', 'array'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'filters.country_id' => ['sometimes', 'integer', 'min:1'],
            'filters.code' => ['sometimes', 'string', 'max:10'],
        ];
    }
}
