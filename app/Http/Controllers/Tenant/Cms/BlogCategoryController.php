<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Cms\StoreBlogCategoryRequest;
use App\Http\Requests\Tenant\Cms\UpdateBlogCategoryRequest;
use App\Http\Resources\Tenant\Cms\BlogCategoryResource;
use App\Models\Tenant\Cms\BlogCategory;
use App\Services\Tenant\Cms\BlogCategoryService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BlogCategoryController extends Controller
{
    public function __construct(private readonly BlogCategoryService $blogCategoryService) {}

    #[Response(status: 200, description: 'Paginated blog categories.', type: 'array{success: true, message: string, data: BlogCategoryResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}')]
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', BlogCategory::class);

        $categories = $this->blogCategoryService->list($request->only(['search', 'is_active', 'per_page']));

        return $this->success(
            BlogCategoryResource::collection($categories->items()),
            'Blog categories retrieved successfully.',
            $this->paginationMeta($categories),
        );
    }

    #[Response(status: 200, description: 'Blog category options.', type: ApiResponseSchema::OPTIONS)]
    public function options(): JsonResponse
    {
        $this->authorize('viewAny', BlogCategory::class);

        return $this->success(
            $this->blogCategoryService->options(),
            'Blog category options retrieved successfully.',
        );
    }

    #[Response(status: 201, description: 'Created blog category.', type: 'array{success: true, message: string, data: BlogCategoryResource, meta: null, errors: null}')]
    public function store(StoreBlogCategoryRequest $request): JsonResponse
    {
        $this->authorize('create', BlogCategory::class);

        return $this->created(
            new BlogCategoryResource($this->blogCategoryService->store($request->validated())),
            'Blog category created successfully.',
        );
    }

    #[Response(status: 200, description: 'A blog category.', type: 'array{success: true, message: string, data: BlogCategoryResource, meta: null, errors: null}')]
    public function show(BlogCategory $blog_category): JsonResponse
    {
        $this->authorize('view', $blog_category);

        return $this->success(
            new BlogCategoryResource($this->blogCategoryService->show($blog_category)),
            'Blog category retrieved successfully.',
        );
    }

    #[Response(status: 200, description: 'Updated blog category.', type: 'array{success: true, message: string, data: BlogCategoryResource, meta: null, errors: null}')]
    public function update(UpdateBlogCategoryRequest $request, BlogCategory $blog_category): JsonResponse
    {
        $this->authorize('update', $blog_category);

        return $this->updated(
            new BlogCategoryResource($this->blogCategoryService->update($blog_category, $request->validated())),
            'Blog category updated successfully.',
        );
    }

    #[Response(status: 200, description: 'Deleted blog category.', type: 'array{success: true, message: string, data: null, meta: null, errors: null}')]
    public function destroy(BlogCategory $blog_category): JsonResponse
    {
        $this->authorize('delete', $blog_category);
        $this->blogCategoryService->destroy($blog_category);

        return $this->deleted('Blog category deleted successfully.');
    }
}
