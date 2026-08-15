<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Catalog;

use App\Enums\Media\MediaCollection;
use App\Http\Resources\Media\MediaResource;
use App\Models\Tenant\ProductBadge;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API resource for product badges.
 *
 * @mixin ProductBadge
 */
class ProductBadgeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ProductBadge $badge */
        $badge = $this->resource;
        $image = $badge->relationLoaded('media')
            ? $badge->getFirstMedia(MediaCollection::Image->value)
            : null;

        return [
            'id' => $badge->id,
            'name' => $badge->name,
            'slug' => $badge->slug,
            'color' => $badge->color,
            'is_active' => (bool) $badge->is_active,
            'sort_order' => $badge->sort_order,
            'image' => $image ? new MediaResource($image) : null,
            'pivot' => $this->when(
                isset($badge->pivot),
                fn () => [
                    'sort_order' => $badge->pivot->sort_order ?? 0,
                ],
            ),
            'products_count' => $this->when(isset($badge->products_count), $badge->products_count),
            'created_at' => $badge->created_at,
            'updated_at' => $badge->updated_at,
        ];
    }
}
