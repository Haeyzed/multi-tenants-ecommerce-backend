<?php

declare(strict_types=1);

namespace App\Models\Landlord;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * SEO metadata for a morphable landlord CONTENT entity.
 *
 * @property int $id
 * @property string $seoable_type
 * @property int $seoable_id
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property string|null $meta_keywords
 * @property string|null $canonical_url
 * @property string|null $og_title
 * @property string|null $og_description
 */
class SeoMeta extends Model
{
    use CentralConnection;

    /**
     * @var string
     */
    protected $table = 'landlord_seo_meta';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'seoable_type',
        'seoable_id',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'canonical_url',
        'og_title',
        'og_description',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'seoable_id' => 'integer',
        ];
    }

    /**
     * Owning model (blog post, page, …).
     *
     * @return MorphTo<Model, $this>
     */
    public function seoable(): MorphTo
    {
        return $this->morphTo();
    }
}
