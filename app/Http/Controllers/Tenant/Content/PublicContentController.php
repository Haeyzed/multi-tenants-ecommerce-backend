<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Content;

use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\Content\BlogCategoryResource;
use App\Http\Resources\Tenant\Content\BlogPostResource;
use App\Http\Resources\Tenant\Content\PageResource;
use App\Services\Tenant\Content\BlogCategoryService;
use App\Services\Tenant\Content\BlogPostService;
use App\Services\Tenant\Content\PageService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public tenant CONTENT storefront endpoints.
 */
class PublicContentController extends Controller
{
    /**
     * Create a new class instance.
     *
     * @param  PageService  $pageService
     * @param  BlogPostService  $blogPostService
     * @param  BlogCategoryService  $blogCategoryService
     */
    public function __construct(
        private readonly PageService $pageService,
        private readonly BlogPostService $blogPostService,
        private readonly BlogCategoryService $blogCategoryService,
    ) {}

    /**
     * Show page.
     *
     * @param  string  $slug
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Published page by slug.', type: 'array{success: true, message: string, data: PageResource, meta: null, errors: null}')]
    public function showPage(string $slug): JsonResponse
    {
        return $this->success(
            new PageResource($this->pageService->showPublicBySlug($slug)),
            'Page retrieved successfully.',
        );
    }

    /**
     * Index posts.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Published blog posts.', type: 'array{success: true, message: string, data: BlogPostResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}')]
    public function indexPosts(Request $request): JsonResponse
    {
        $posts = $this->blogPostService->listPublic($request->only(['search', 'blog_category_id', 'per_page']));

        return $this->success(
            BlogPostResource::collection($posts->items()),
            'Blog posts retrieved successfully.',
            $this->paginationMeta($posts),
        );
    }

    /**
     * Show post.
     *
     * @param  string  $slug
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Published blog post by slug.', type: 'array{success: true, message: string, data: BlogPostResource, meta: null, errors: null}')]
    public function showPost(string $slug): JsonResponse
    {
        return $this->success(
            new BlogPostResource($this->blogPostService->showPublicBySlug($slug)),
            'Blog post retrieved successfully.',
        );
    }

    /**
     * Index categories.
     *
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Active blog categories.', type: 'array{success: true, message: string, data: BlogCategoryResource[], meta: null, errors: null}')]
    public function indexCategories(): JsonResponse
    {
        return $this->success(
            BlogCategoryResource::collection($this->blogCategoryService->listPublic()),
            'Blog categories retrieved successfully.',
        );
    }
}
