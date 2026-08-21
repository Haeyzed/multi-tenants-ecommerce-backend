<?php

declare(strict_types=1);

namespace App\Http\Controllers\Landlord\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Landlord\Cms\StoreBlogCategoryRequest;
use App\Http\Requests\Landlord\Cms\UpdateBlogCategoryRequest;
use App\Http\Resources\Landlord\Cms\BlogCategoryResource;
use App\Models\Landlord\Cms\BlogCategory;
use App\Services\Landlord\Cms\BlogCategoryService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Landlord BlogCategoryController endpoints.
 */
class BlogCategoryController extends Controller
{
    /**
     * Create a new class instance.
     *
     * @param  BlogCategoryService  $blogCategoryService
     */
    public function __construct(private readonly BlogCategoryService $blogCategoryService) {}

    /**
     * List resources with pagination and filters.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Paginated blog categories.', type: 'array{success: true, message: string, data: BlogCategoryResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}')]
    public function index(Request $request): JsonResponse
    {
        $categories = $this->blogCategoryService->list($request->only(['search', 'is_active', 'per_page']));

        return $this->success(
            BlogCategoryResource::collection($categories->items()),
            'Blog categories retrieved successfully.',
            $this->paginationMeta($categories),
        );
    }

    /**
     * Return options for select inputs.
     *
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Blog category options.', type: ApiResponseSchema::OPTIONS)]
    public function options(): JsonResponse
    {
        return $this->success(
            $this->blogCategoryService->options(),
            'Blog category options retrieved successfully.',
        );
    }

    /**
     * Create a resource.
     *
     * @param  StoreBlogCategoryRequest  $request
     * @return JsonResponse
     */
    #[Response(status: 201, description: 'Created blog category.', type: 'array{success: true, message: string, data: BlogCategoryResource, meta: null, errors: null}')]
    public function store(StoreBlogCategoryRequest $request): JsonResponse
    {
        return $this->created(
            new BlogCategoryResource($this->blogCategoryService->store($request->validated())),
            'Blog category created successfully.',
        );
    }

    /**
     * Retrieve a single resource.
     *
     * @param  BlogCategory  $blog_category
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'A blog category.', type: 'array{success: true, message: string, data: BlogCategoryResource, meta: null, errors: null}')]
    public function show(BlogCategory $blog_category): JsonResponse
    {
        return $this->success(
            new BlogCategoryResource($this->blogCategoryService->show($blog_category)),
            'Blog category retrieved successfully.',
        );
    }

    /**
     * Update a resource.
     *
     * @param  UpdateBlogCategoryRequest  $request
     * @param  BlogCategory  $blog_category
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Updated blog category.', type: 'array{success: true, message: string, data: BlogCategoryResource, meta: null, errors: null}')]
    public function update(UpdateBlogCategoryRequest $request, BlogCategory $blog_category): JsonResponse
    {
        return $this->updated(
            new BlogCategoryResource($this->blogCategoryService->update($blog_category, $request->validated())),
            'Blog category updated successfully.',
        );
    }

    /**
     * Delete a resource.
     *
     * @param  BlogCategory  $blog_category
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Deleted blog category.', type: 'array{success: true, message: string, data: null, meta: null, errors: null}')]
    public function destroy(BlogCategory $blog_category): JsonResponse
    {
        $this->blogCategoryService->destroy($blog_category);

        return $this->deleted('Blog category deleted successfully.');
    }
}
