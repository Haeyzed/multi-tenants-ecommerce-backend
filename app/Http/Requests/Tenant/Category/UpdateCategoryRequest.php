<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Category;

use App\Http\Requests\BaseRequest;
use App\Support\Media\MediaValidation;
use Illuminate\Validation\Rule;

/**
 * Validates category update payloads (JSON or multipart).
 */
class UpdateCategoryRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $category = $this->route('category');
        $categoryId = is_object($category) ? $category->getKey() : $category;
        $parentId = $this->has('parent_id')
            ? $this->input('parent_id')
            : (is_object($category) ? $category->parent_id : null);

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('categories', 'name')
                    ->ignore($categoryId)
                    ->where(fn ($query) => $parentId === null
                        ? $query->whereNull('parent_id')
                        : $query->where('parent_id', $parentId)),
            ],
            'parent_id' => [
                'sometimes',
                'nullable',
                'integer',
                'exists:categories,id',
                Rule::notIn([(int) $categoryId]),
            ],
            'description' => ['sometimes', 'nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'image' => MediaValidation::image(required: false),
        ];
    }
}
