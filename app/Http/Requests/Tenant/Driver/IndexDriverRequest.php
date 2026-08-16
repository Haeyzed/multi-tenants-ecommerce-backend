<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Driver;

use App\Enums\Tenant\Driver\DriverAvailability;
use App\Enums\Tenant\Driver\DriverStatus;
use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

/**
 * Validates staff driver index filters.
 */
class IndexDriverRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'nullable', 'string', Rule::enum(DriverStatus::class)],
            'availability' => ['sometimes', 'nullable', 'string', Rule::enum(DriverAvailability::class)],
            'sort' => ['sometimes', 'nullable', 'string', 'max:50'],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
