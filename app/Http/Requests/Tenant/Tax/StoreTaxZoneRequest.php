<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Tax;

use App\Http\Requests\BaseRequest;

class StoreTaxZoneRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'locations' => ['sometimes', 'array'],
            'locations.*.country_id' => ['sometimes', 'nullable', 'integer'],
            'locations.*.state_id' => ['sometimes', 'nullable', 'integer'],
            'locations.*.city_id' => ['sometimes', 'nullable', 'integer'],
        ];
    }
}
