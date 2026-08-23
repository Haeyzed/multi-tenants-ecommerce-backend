<?php

declare(strict_types=1);

namespace App\Http\Requests\Landlord\Content;

use App\Enums\Content\ContentStatus;
use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class StorePageRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('landlord_pages', 'slug')],
            'content' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', 'string', Rule::enum(ContentStatus::class)],
            'published_at' => ['sometimes', 'nullable', 'date'],
            'seo' => ['sometimes', 'nullable', 'array'],
            'seo.meta_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'seo.meta_description' => ['sometimes', 'nullable', 'string'],
            'seo.meta_keywords' => ['sometimes', 'nullable', 'string', 'max:255'],
            'seo.canonical_url' => ['sometimes', 'nullable', 'string', 'max:255'],
            'seo.og_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'seo.og_description' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
