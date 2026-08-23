<?php

declare(strict_types=1);

namespace App\Http\Requests\Landlord\Cms;

use App\Enums\Cms\CmsContentStatus;
use App\Http\Requests\BaseRequest;
use App\Models\Landlord\Cms\BlogPost;
use Illuminate\Validation\Rule;

class UpdateBlogPostRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var BlogPost $post */
        $post = $this->route('blog_post');

        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('landlord_blog_posts', 'slug')->ignore($post->id)],
            'excerpt' => ['sometimes', 'nullable', 'string'],
            'content' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', 'string', Rule::enum(CmsContentStatus::class)],
            'published_at' => ['sometimes', 'nullable', 'date'],
            'author_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'blog_category_id' => ['sometimes', 'nullable', 'integer', 'exists:landlord_blog_categories,id'],
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
