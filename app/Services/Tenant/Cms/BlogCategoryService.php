<?php

declare(strict_types=1);

namespace App\Services\Tenant\Cms;

use App\Models\Tenant\Cms\BlogCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Tenant blog category CRUD.
 */
class BlogCategoryService
{
    /**
     * Retrieve a paginated list of resources.
     *
     * @param  array{search?: string|null, is_active?: bool|null, per_page?: int|null}  $params
     * @return LengthAwarePaginator<int, BlogCategory>
     */
    public function list(array $params = []): LengthAwarePaginator
    {
        return BlogCategory::query()
            ->filter($params)
            ->orderBy('name')
            ->paginate($this->perPage($params));
    }

    /**
     * Active categories for public storefront.
     *
     * @return Collection<int, BlogCategory>
     */
    public function listPublic(): Collection
    {
        return BlogCategory::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * Return options for select inputs.
     *
     * @return Collection<int, array{label: string, value: int}>
     */
    public function options(): Collection
    {
        return BlogCategory::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (BlogCategory $category): array => [
                'label' => $category->name,
                'value' => $category->id,
            ])
            ->values();
    }

    /**
     * Create a resource.
     *
     * @param  array{name: string, slug?: string|null, description?: string|null, is_active?: bool}  $data
     * @return BlogCategory
     */
    public function store(array $data): BlogCategory
    {
        return BlogCategory::query()->create([
            'name' => $data['name'],
            'slug' => $data['slug'] ?? null,
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /**
     * Retrieve a single resource.
     *
     * @param  BlogCategory  $category
     * @return BlogCategory
     */
    public function show(BlogCategory $category): BlogCategory
    {
        return $category->loadCount('posts');
    }

    /**
     * Update a resource.
     *
     * @param  BlogCategory  $category
     * @param  array{name?: string, slug?: string|null, description?: string|null, is_active?: bool}  $data
     * @return BlogCategory
     */
    public function update(BlogCategory $category, array $data): BlogCategory
    {
        $category->fill($data);
        $category->save();

        return $category->fresh() ?? $category;
    }

    /**
     * Delete a resource.
     *
     * @param  BlogCategory  $category
     * @return void
     */
    public function destroy(BlogCategory $category): void
    {
        $category->delete();
    }

    /**
     * Resolve the page size for paginated listings.
     *
     * @param  array{per_page?: int|null}  $params
     * @return int
     */
    protected function perPage(array $params): int
    {
        return max(1, min((int) ($params['per_page'] ?? 15), 100));
    }
}
