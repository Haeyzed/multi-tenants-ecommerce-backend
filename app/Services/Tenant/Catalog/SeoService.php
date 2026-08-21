<?php

declare(strict_types=1);

namespace App\Services\Tenant\Catalog;

use App\Models\Tenant\SeoMeta;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * SEO meta show/upsert for any HasSeo model.
 */
class SeoService
{
    /**
     * Show SEO meta for a model (null when none exists).
     *
     * @param  Model&object{seo(): MorphOne}  $model
     * @return ?SeoMeta
     */
    public function show(Model $model): ?SeoMeta
    {
        /** @var SeoMeta|null $seo */
        $seo = $model->seo;

        return $seo;
    }

    /**
     * Create or update SEO meta for a model.
     *
     * @param  Model&object{seo(): MorphOne}  $model
     * @param  array{ meta_title?: string|null, meta_description?: string|null, meta_keywords?: string|null, canonical_url?: string|null, og_title?: string|null, og_description?: string|null }  $data
     * @return SeoMeta
     */
    public function upsert(Model $model, array $data): SeoMeta
    {
        /** @var SeoMeta $seo */
        $seo = $model->seo()->updateOrCreate(
            [],
            [
                'meta_title' => $data['meta_title'] ?? null,
                'meta_description' => $data['meta_description'] ?? null,
                'meta_keywords' => $data['meta_keywords'] ?? null,
                'canonical_url' => $data['canonical_url'] ?? null,
                'og_title' => $data['og_title'] ?? null,
                'og_description' => $data['og_description'] ?? null,
            ],
        );

        return $seo;
    }
}
