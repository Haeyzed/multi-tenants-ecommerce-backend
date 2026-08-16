<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Cms;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class StoreBlogCategoryRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('blog_categories', 'slug')],
            'description' => ['sometimes', 'nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
