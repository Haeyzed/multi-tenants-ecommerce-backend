<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Cms;

use App\Enums\Media\MediaCollection;
use App\Models\Tenant\Content\BlogPost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BlogPost
 */
class BlogPostResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var BlogPost $post */
        $post = $this->resource;

        return [
            'id' => $post->id,
            'title' => $post->title,
            'slug' => $post->slug,
            'excerpt' => $post->excerpt,
            'content' => $post->content,
            'status' => $post->status,
            'published_at' => $post->published_at,
            'author_id' => $post->author_id,
            'blog_category_id' => $post->blog_category_id,
            'featured_image_url' => $post->getFirstMediaUrl(MediaCollection::FeaturedImage->value) ?: null,
            'category' => $this->whenLoaded('category', fn () => $post->category === null ? null : [
                'id' => $post->category->id,
                'name' => $post->category->name,
                'slug' => $post->category->slug,
            ]),
            'author' => $this->whenLoaded('author', fn () => $post->author === null ? null : [
                'id' => $post->author->id,
                'first_name' => $post->author->first_name,
                'last_name' => $post->author->last_name,
                'email' => $post->author->email,
            ]),
            'seo' => $this->whenLoaded('seo', fn () => $post->seo === null ? null : [
                'meta_title' => $post->seo->meta_title,
                'meta_description' => $post->seo->meta_description,
                'meta_keywords' => $post->seo->meta_keywords,
                'canonical_url' => $post->seo->canonical_url,
                'og_title' => $post->seo->og_title,
                'og_description' => $post->seo->og_description,
            ]),
            'created_at' => $post->created_at,
            'updated_at' => $post->updated_at,
        ];
    }
}
