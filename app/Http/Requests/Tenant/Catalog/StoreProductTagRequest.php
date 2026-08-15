<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Catalog;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

/**
 * Validates tag creation payloads.
 */
class StoreProductTagRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('product_tags', 'name')],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('product_tags', 'slug')],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
