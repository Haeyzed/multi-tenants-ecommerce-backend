<?php

declare(strict_types=1);

namespace App\Http\Requests\Landlord\Domain;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

/**
 * Validates domain creation payloads.
 */
class StoreDomainRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'domain' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]([a-z0-9\-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9\-]*[a-z0-9])?)+$/i', Rule::unique('domains', 'domain')],
            'is_primary' => ['sometimes', 'boolean'],
        ];
    }
}
