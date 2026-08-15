<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Catalog;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

/**
 * Validates tag update payloads.
 */
class UpdateProductTagRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var int|string|null $tagId */
        $tagId = $this->route('tag');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('product_tags', 'name')->ignore($tagId)],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('product_tags', 'slug')->ignore($tagId)],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
