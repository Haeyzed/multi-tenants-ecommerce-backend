<?php

declare(strict_types=1);

namespace App\Http\Requests\Landlord\Domain;

use App\Http\Requests\BaseRequest;

/**
 * Validates domain index query params.
 */
class IndexDomainRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
