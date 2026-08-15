<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Storefront;

use App\Http\Requests\BaseRequest;

/**
 * Validates storefront list query params.
 */
class IndexStorefrontRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'brand_id' => ['sometimes', 'nullable', 'integer'],
            'category_id' => ['sometimes', 'nullable', 'integer'],
            'collection_id' => ['sometimes', 'nullable', 'integer'],
            'tag_id' => ['sometimes', 'nullable', 'integer'],
            'is_featured' => ['sometimes', 'nullable', 'boolean'],
            'parent_id' => ['sometimes', 'nullable', 'integer'],
            'sort' => ['sometimes', 'nullable', 'string', 'max:50'],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
