<?php

declare(strict_types=1);

namespace App\Http\Resources\Landlord\Cms;

use App\Models\Landlord\Cms\BlogCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BlogCategory
 */
class BlogCategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var BlogCategory $category */
        $category = $this->resource;

        return [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'is_active' => $category->is_active,
            'posts_count' => $this->when(isset($category->posts_count), $category->posts_count),
            'created_at' => $category->created_at,
            'updated_at' => $category->updated_at,
        ];
    }
}
