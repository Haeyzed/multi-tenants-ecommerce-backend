<?php

declare(strict_types=1);

namespace App\Services\Landlord\Cms;

use App\Models\Landlord\Cms\BlogCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Landlord blog category CRUD.
 */
class BlogCategoryService
{
    /**
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
     * @param  array{name: string, slug?: string|null, description?: string|null, is_active?: bool}  $data
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

    public function show(BlogCategory $category): BlogCategory
    {
        return $category->loadCount('posts');
    }

    /**
     * @param  array{name?: string, slug?: string|null, description?: string|null, is_active?: bool}  $data
     */
    public function update(BlogCategory $category, array $data): BlogCategory
    {
        $category->fill($data);
        $category->save();

        return $category->fresh() ?? $category;
    }

    public function destroy(BlogCategory $category): void
    {
        $category->delete();
    }

    /**
     * @param  array{per_page?: int|null}  $params
     */
    protected function perPage(array $params): int
    {
        return max(1, min((int) ($params['per_page'] ?? 15), 100));
    }
}
