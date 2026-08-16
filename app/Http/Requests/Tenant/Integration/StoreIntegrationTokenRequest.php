<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Integration;

use App\Http\Requests\BaseRequest;

/**
 * Validates integration API token creation.
 */
class StoreIntegrationTokenRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z0-9 _.-]+$/'],
            'abilities' => ['sometimes', 'array', 'min:1'],
            'abilities.*' => ['string', 'max:100'],
        ];
    }
}
