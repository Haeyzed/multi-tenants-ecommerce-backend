<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public\Content;

use App\Http\Controllers\Controller;
use App\Http\Resources\Landlord\Content\BlogPostResource;
use App\Http\Resources\Landlord\Content\PageResource;
use App\Services\Landlord\Content\BlogPostService;
use App\Services\Landlord\Content\PageService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public landlord CONTENT endpoints.
 */
class PublicContentController extends Controller
{
    public function __construct(
        private readonly PageService $pageService,
        private readonly BlogPostService $blogPostService,
    ) {}

    #[Response(status: 200, description: 'Published page by slug.', type: 'array{success: true, message: string, data: PageResource, meta: null, errors: null}')]
    public function showPage(string $slug): JsonResponse
    {
        return $this->success(
            new PageResource($this->pageService->showPublicBySlug($slug)),
            'Page retrieved successfully.',
        );
    }

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
}
