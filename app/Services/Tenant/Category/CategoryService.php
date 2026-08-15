<?php

declare(strict_types=1);

namespace App\Services\Tenant\Category;

use App\Enums\Media\MediaCollection;
use App\Models\Tenant\Category;
use App\Services\Media\MediaService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Tenant category catalog operations including hierarchy rules.
 */
class CategoryService
{
    public function __construct(private readonly MediaService $mediaService) {}

    /**
     * Paginate categories with search, filters, and sorts.
     *
     * @param  array{
     *     search?: string|null,
     *     is_active?: bool|null,
     *     parent_id?: int|null,
     *     root?: bool|null,
     *     sort?: string|null,
     *     per_page?: int|null
     * }  $params
     * @return LengthAwarePaginator<int, Category>
     */
    public function list(array $params = []): LengthAwarePaginator
    {
        return Category::query()
            ->with(['media', 'parent'])
            ->filter($params)
            ->applySort($params['sort'] ?? null)
            ->paginate($this->perPage($params));
    }

    /**
     * Build a nested category tree from root nodes.
     *
     * Loads all categories once and nests in memory to avoid N+1 recursion.
     *
     * @param  array{is_active?: bool|null}  $params
     * @return Collection<int, Category>
     */
    public function tree(array $params = []): Collection
    {
        $categories = Category::query()
            ->with('media')
            ->when(array_key_exists('is_active', $params) && $params['is_active'] !== null, function ($query) use ($params): void {
                $query->where('is_active', (bool) $params['is_active']);
            })
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return $this->nestTree($categories);
    }

    /**
     * Category options for select inputs.
     *
     * @param  array{search?: string|null, is_active?: bool|null, parent_id?: int|null, root?: bool|null}  $params
     * @return Collection<int, array{label: string, value: int}>
     */
    public function options(array $params = []): Collection
    {
        return Category::query()
            ->filter($params)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'parent_id'])
            ->map(fn (Category $category): array => [
                'label' => $category->name,
                'value' => $category->id,
            ])
            ->values();
    }

    /**
     * Create a category and optionally attach an image.
     *
     * @param  array{
     *     name: string,
     *     parent_id?: int|null,
     *     description?: string|null,
     *     is_active?: bool,
     *     sort_order?: int
     * }  $data
     *
     * @throws ValidationException
     */
    public function store(array $data, ?UploadedFile $image = null): Category
    {
        $parentId = $data['parent_id'] ?? null;

        if ($parentId !== null) {
            $this->assertParentExists((int) $parentId);
        }

        return DB::transaction(function () use ($data, $image, $parentId): Category {
            /** @var Category $category */
            $category = Category::query()->create([
                'name' => $data['name'],
                'parent_id' => $parentId,
                'description' => $data['description'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'sort_order' => $data['sort_order'] ?? 0,
            ]);

            if ($image !== null) {
                $this->mediaService->replace($category, $image, MediaCollection::Image);
            }

            return $category->load(['media', 'parent']);
        });
    }

    /**
     * Retrieve a category with relations loaded.
     */
    public function show(Category $category): Category
    {
        return $category->load(['media', 'parent', 'children']);
    }

    /**
     * Update a category, validating hierarchy changes.
     *
     * @param  array{
     *     name?: string,
     *     parent_id?: int|null,
     *     description?: string|null,
     *     is_active?: bool,
     *     sort_order?: int
     * }  $data
     *
     * @throws ValidationException
     */
    public function update(Category $category, array $data, ?UploadedFile $image = null): Category
    {
        if (array_key_exists('parent_id', $data)) {
            $this->assertValidParent($category, $data['parent_id']);
        }

        return DB::transaction(function () use ($category, $data, $image): Category {
            $category->fill($data);
            $category->save();

            if ($image !== null) {
                $this->mediaService->replace($category, $image, MediaCollection::Image);
            }

            return $category->fresh(['media', 'parent', 'children']) ?? $category->load(['media', 'parent', 'children']);
        });
    }

    /**
     * Delete a category when it has no children or products.
     *
     * @throws ValidationException
     */
    public function destroy(Category $category): void
    {
        if ($category->children()->exists()) {
            throw ValidationException::withMessages([
                'category' => 'Cannot delete a category that has child categories.',
            ]);
        }

        if ($this->hasAssociatedProducts($category)) {
            throw ValidationException::withMessages([
                'category' => 'Cannot delete a category that has associated products.',
            ]);
        }

        DB::transaction(function () use ($category): void {
            $this->mediaService->removeCollection($category, MediaCollection::Image);
            $category->delete();
        });
    }

    /**
     * List immediate children of a category.
     *
     * @return Collection<int, Category>
     */
    public function children(Category $category): Collection
    {
        return $category->children()->with('media')->get();
    }

    /**
     * Replace the category image.
     */
    public function storeImage(Category $category, UploadedFile $image): Category
    {
        $this->mediaService->replace($category, $image, MediaCollection::Image);

        return $category->fresh(['media']) ?? $category->load('media');
    }

    /**
     * Remove the category image.
     */
    public function destroyImage(Category $category): Category
    {
        $this->mediaService->removeCollection($category, MediaCollection::Image);

        return $category->fresh(['media']) ?? $category->load('media');
    }

    /**
     * Ensure parent_id exists in the current tenant database.
     *
     * @throws ValidationException
     */
    public function assertParentExists(int $parentId): void
    {
        if (! Category::query()->whereKey($parentId)->exists()) {
            throw ValidationException::withMessages([
                'parent_id' => 'The selected parent category is invalid.',
            ]);
        }
    }

    /**
     * Prevent self-parenting and circular hierarchy.
     *
     * @throws ValidationException
     */
    public function assertValidParent(Category $category, mixed $parentId): void
    {
        if ($parentId === null || $parentId === '') {
            return;
        }

        $parentId = (int) $parentId;

        if ($parentId === (int) $category->id) {
            throw ValidationException::withMessages([
                'parent_id' => 'A category cannot be its own parent.',
            ]);
        }

        $this->assertParentExists($parentId);

        if ($this->wouldCreateCycle($category, $parentId)) {
            throw ValidationException::withMessages([
                'parent_id' => 'Cannot set parent: that would create a circular hierarchy.',
            ]);
        }
    }

    /**
     * Walk ancestors of the proposed parent to detect cycles.
     */
    protected function wouldCreateCycle(Category $category, int $parentId): bool
    {
        $currentId = $parentId;
        $guard = 0;

        while ($currentId !== null && $guard < 100) {
            if ($currentId === (int) $category->id) {
                return true;
            }

            $currentId = Category::query()->whereKey($currentId)->value('parent_id');
            $currentId = $currentId !== null ? (int) $currentId : null;
            $guard++;
        }

        return false;
    }

    /**
     * Nest a flat category collection into a root tree.
     *
     * @param  Collection<int, Category>  $categories
     * @return Collection<int, Category>
     */
    protected function nestTree(Collection $categories): Collection
    {
        $byParent = $categories->groupBy(fn (Category $category): string => (string) ($category->parent_id ?? 'root'));

        $attach = function (Category $node) use (&$attach, $byParent): Category {
            $children = ($byParent->get((string) $node->id) ?? collect())
                ->map(fn (Category $child): Category => $attach($child))
                ->values();

            $node->setRelation('children', $children);

            return $node;
        };

        return ($byParent->get('root') ?? collect())
            ->map(fn (Category $root): Category => $attach($root))
            ->values();
    }

    /**
     * Whether the category has products (prepared for Product module).
     */
    protected function hasAssociatedProducts(Category $category): bool
    {
        if (! method_exists($category, 'products')) {
            return false;
        }

        return $category->products()->exists();
    }

    /**
     * @param  array{per_page?: int|null}  $params
     */
    protected function perPage(array $params): int
    {
        return max(1, min((int) ($params['per_page'] ?? 15), 100));
    }
}
