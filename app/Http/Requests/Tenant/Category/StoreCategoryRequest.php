<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Category;

use App\Http\Requests\BaseRequest;
use App\Support\Media\MediaValidation;
use Illuminate\Validation\Rule;

/**
 * Validates category creation payloads (JSON or multipart).
 *
 * Name uniqueness is scoped to the same parent (siblings only).
 * Slug uniqueness is tenant-wide via Spatie Sluggable.
 */
class StoreCategoryRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $parentId = $this->input('parent_id');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('categories', 'name')->where(fn ($query) => $parentId === null
                    ? $query->whereNull('parent_id')
                    : $query->where('parent_id', $parentId)),
            ],
            'parent_id' => ['sometimes', 'nullable', 'integer', 'exists:categories,id'],
            'description' => ['sometimes', 'nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'image' => MediaValidation::image(required: false),
        ];
    }
}
