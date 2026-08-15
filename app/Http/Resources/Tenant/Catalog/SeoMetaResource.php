<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Catalog;

use App\Models\Tenant\SeoMeta;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API resource for SEO meta.
 *
 * @mixin SeoMeta
 */
class SeoMetaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var SeoMeta $seo */
        $seo = $this->resource;

        return [
            'id' => $seo->id,
            'meta_title' => $seo->meta_title,
            'meta_description' => $seo->meta_description,
            'meta_keywords' => $seo->meta_keywords,
            'canonical_url' => $seo->canonical_url,
            'og_title' => $seo->og_title,
            'og_description' => $seo->og_description,
            'created_at' => $seo->created_at,
            'updated_at' => $seo->updated_at,
        ];
    }
}
