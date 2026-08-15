<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Category;

use App\Enums\Media\MediaCollection;
use App\Http\Resources\Media\MediaResource;
use App\Models\Tenant\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API resource for tenant categories.
 *
 * products_count reflects direct product attachments only (not descendants).
 *
 * @mixin Category
 */
class CategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Category $category */
        $category = $this->resource;
        $image = $category->getFirstMedia(MediaCollection::Image->value);

        return [
            'id' => $category->id,
            'parent_id' => $category->parent_id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'is_active' => (bool) $category->is_active,
            'sort_order' => $category->sort_order,
            'image' => $image ? new MediaResource($image) : null,
            'parent' => $this->whenLoaded('parent', fn () => new self($category->parent)),
            'children' => self::collection($this->whenLoaded('children')),
            'products_count' => $this->when(isset($category->products_count), $category->products_count),
            'created_at' => $category->created_at,
            'updated_at' => $category->updated_at,
        ];
    }
}
