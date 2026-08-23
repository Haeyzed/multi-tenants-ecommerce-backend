<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Cms;

use App\Enums\Cms\CmsContentStatus;
use App\Http\Requests\BaseRequest;
use App\Models\Tenant\Content\Page;
use Illuminate\Validation\Rule;

class UpdatePageRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Page $page */
        $page = $this->route('page');

        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('pages', 'slug')->ignore($page->id)],
            'content' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', 'string', Rule::enum(CmsContentStatus::class)],
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
