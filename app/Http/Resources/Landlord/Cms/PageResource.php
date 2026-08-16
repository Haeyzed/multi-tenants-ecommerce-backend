<?php

declare(strict_types=1);

namespace App\Http\Resources\Landlord\Cms;

use App\Enums\Media\MediaCollection;
use App\Models\Landlord\Cms\Page;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Page
 */
class PageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Page $page */
        $page = $this->resource;

        return [
            'id' => $page->id,
            'title' => $page->title,
            'slug' => $page->slug,
            'content' => $page->content,
            'status' => $page->status,
            'published_at' => $page->published_at,
            'featured_image_url' => $page->getFirstMediaUrl(MediaCollection::FeaturedImage->value) ?: null,
            'seo' => $this->whenLoaded('seo', fn () => $page->seo === null ? null : [
                'meta_title' => $page->seo->meta_title,
                'meta_description' => $page->seo->meta_description,
                'meta_keywords' => $page->seo->meta_keywords,
                'canonical_url' => $page->seo->canonical_url,
                'og_title' => $page->seo->og_title,
                'og_description' => $page->seo->og_description,
            ]),
            'created_at' => $page->created_at,
            'updated_at' => $page->updated_at,
        ];
    }
}
