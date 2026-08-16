<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Pos;

use App\Http\Requests\BaseRequest;

class PosCatalogSearchRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
