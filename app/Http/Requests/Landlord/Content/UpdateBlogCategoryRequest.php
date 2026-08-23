<?php

declare(strict_types=1);

namespace App\Http\Requests\Landlord\Content;

use App\Http\Requests\BaseRequest;
use App\Models\Landlord\Content\BlogCategory;
use Illuminate\Validation\Rule;

class UpdateBlogCategoryRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var BlogCategory $category */
        $category = $this->route('blog_category');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('landlord_blog_categories', 'slug')->ignore($category->id)],
            'description' => ['sometimes', 'nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
