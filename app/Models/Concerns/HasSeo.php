<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Tenant\SeoMeta;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * Adds a morphOne SEO meta relationship.
 */
trait HasSeo
{
    /**
     * SEO metadata for this model.
     *
     * @return MorphOne<SeoMeta, $this>
     */
    public function seo(): MorphOne
    {
        return $this->morphOne(SeoMeta::class, 'seoable');
    }
}
