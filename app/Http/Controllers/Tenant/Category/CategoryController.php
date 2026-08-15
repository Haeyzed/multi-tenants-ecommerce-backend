<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Category;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Category\IndexCategoryRequest;
use App\Http\Requests\Tenant\Category\StoreCategoryImageRequest;
use App\Http\Requests\Tenant\Category\StoreCategoryRequest;
use App\Http\Requests\Tenant\Category\UpdateCategoryRequest;
use App\Http\Resources\Tenant\Category\CategoryResource;
use App\Http\Resources\Tenant\Category\CategoryTreeResource;
use App\Models\Tenant\Category;
use App\Services\Tenant\Category\CategoryService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;

/**
 * Tenant category catalog endpoints.
 */
class CategoryController extends Controller
{
    public function __construct(private readonly CategoryService $categoryService) {}

    /**
     * List categories with pagination, search, and filters.
     */
    #[Response(
        status: 200,
        description: 'Paginated list of categories.',
        type: 'array{success: true, message: string, data: CategoryResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}',
    )]
    public function index(IndexCategoryRequest $request): JsonResponse
    {
        $categories = $this->categoryService->list($request->validated());

        return $this->success(
            CategoryResource::collection($categories->items()),
            'Categories retrieved successfully.',
            $this->paginationMeta($categories),
        );
    }

    /**
     * Hierarchical category tree.
     */
    #[Response(
        status: 200,
        description: 'Nested category tree.',
        type: 'array{success: true, message: string, data: CategoryTreeResource[], meta: null, errors: null}',
    )]
    public function tree(IndexCategoryRequest $request): JsonResponse
    {
        return $this->success(
            CategoryTreeResource::collection($this->categoryService->tree($request->validated())),
            'Category tree retrieved successfully.',
        );
    }

    /**
     * Category options for select inputs.
     */
    #[Response(
        status: 200,
        description: 'Category options.',
        type: ApiResponseSchema::OPTIONS,
    )]
    public function options(IndexCategoryRequest $request): JsonResponse
    {
        return $this->success(
            $this->categoryService->options($request->validated()),
            'Category options retrieved successfully.',
        );
    }

    /**
     * Create a category (supports multipart image upload).
     */
    #[Response(
        status: 201,
        description: 'Created category.',
        type: 'array{success: true, message: string, data: CategoryResource, meta: null, errors: null}',
    )]
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $data = $request->safe()->except(['image']);
        $category = $this->categoryService->store($data, $request->file('image'));

        return $this->created(
            new CategoryResource($category),
            'Category created successfully.',
        );
    }

    /**
     * Show a category.
     */
    #[Response(
        status: 200,
        description: 'A single category.',
        type: 'array{success: true, message: string, data: CategoryResource, meta: null, errors: null}',
    )]
    public function show(Category $category): JsonResponse
    {
        return $this->success(
            new CategoryResource($this->categoryService->show($category)),
            'Category retrieved successfully.',
        );
    }

    /**
     * Immediate children of a category.
     */
    #[Response(
        status: 200,
        description: 'Child categories.',
        type: 'array{success: true, message: string, data: CategoryResource[], meta: null, errors: null}',
    )]
    public function children(Category $category): JsonResponse
    {
        return $this->success(
            CategoryResource::collection($this->categoryService->children($category)),
            'Category children retrieved successfully.',
        );
    }

    /**
     * Update a category (supports multipart image upload).
     */
    #[Response(
        status: 200,
        description: 'Updated category.',
        type: 'array{success: true, message: string, data: CategoryResource, meta: null, errors: null}',
    )]
    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        $data = $request->safe()->except(['image']);
        $category = $this->categoryService->update($category, $data, $request->file('image'));

        return $this->updated(
            new CategoryResource($category),
            'Category updated successfully.',
        );
    }

    /**
     * Delete a category.
     */
    #[Response(
        status: 200,
        description: 'Category deleted.',
        type: 'array{success: true, message: string, data: null, meta: null, errors: null}',
    )]
    public function destroy(Category $category): JsonResponse
    {
        $this->categoryService->destroy($category);

        return $this->deleted('Category deleted successfully.');
    }

    /**
     * Upload or replace a category image.
     */
    #[Response(
        status: 200,
        description: 'Category with updated image.',
        type: 'array{success: true, message: string, data: CategoryResource, meta: null, errors: null}',
    )]
    public function storeImage(StoreCategoryImageRequest $request, Category $category): JsonResponse
    {
        /** @var UploadedFile $image */
        $image = $request->file('image');

        return $this->updated(
            new CategoryResource($this->categoryService->storeImage($category, $image)),
            'Category image updated successfully.',
        );
    }

    /**
     * Remove a category image.
     */
    #[Response(
        status: 200,
        description: 'Category with image removed.',
        type: 'array{success: true, message: string, data: CategoryResource, meta: null, errors: null}',
    )]
    public function destroyImage(Category $category): JsonResponse
    {
        return $this->updated(
            new CategoryResource($this->categoryService->destroyImage($category)),
            'Category image deleted successfully.',
        );
    }
}
