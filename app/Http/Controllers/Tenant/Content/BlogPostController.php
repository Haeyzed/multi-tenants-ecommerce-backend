<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Cms\StoreBlogPostRequest;
use App\Http\Requests\Tenant\Cms\StoreFeaturedImageRequest;
use App\Http\Requests\Tenant\Cms\UpdateBlogPostRequest;
use App\Http\Resources\Tenant\Cms\BlogPostResource;
use App\Models\Tenant\Content\BlogPost;
use App\Services\Tenant\Cms\BlogPostService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tenant BlogPostController endpoints.
 */
class BlogPostController extends Controller
{
    /**
     * Create a new class instance.
     *
     * @param  BlogPostService  $blogPostService
     */
    public function __construct(private readonly BlogPostService $blogPostService) {}

    /**
     * List resources with pagination and filters.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Paginated blog posts.', type: 'array{success: true, message: string, data: BlogPostResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}')]
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', BlogPost::class);

        $posts = $this->blogPostService->list($request->only(['search', 'status', 'blog_category_id', 'per_page']));

        return $this->success(
            BlogPostResource::collection($posts->items()),
            'Blog posts retrieved successfully.',
            $this->paginationMeta($posts),
        );
    }

    /**
     * Create a resource.
     *
     * @param  StoreBlogPostRequest  $request
     * @return JsonResponse
     */
    #[Response(status: 201, description: 'Created blog post.', type: 'array{success: true, message: string, data: BlogPostResource, meta: null, errors: null}')]
    public function store(StoreBlogPostRequest $request): JsonResponse
    {
        $this->authorize('create', BlogPost::class);

        return $this->created(
            new BlogPostResource($this->blogPostService->store($request->validated())),
            'Blog post created successfully.',
        );
    }

    /**
     * Retrieve a single resource.
     *
     * @param  BlogPost  $blog_post
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'A blog post.', type: 'array{success: true, message: string, data: BlogPostResource, meta: null, errors: null}')]
    public function show(BlogPost $blog_post): JsonResponse
    {
        $this->authorize('view', $blog_post);

        return $this->success(
            new BlogPostResource($this->blogPostService->show($blog_post)),
            'Blog post retrieved successfully.',
        );
    }

    /**
     * Update a resource.
     *
     * @param  UpdateBlogPostRequest  $request
     * @param  BlogPost  $blog_post
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Updated blog post.', type: 'array{success: true, message: string, data: BlogPostResource, meta: null, errors: null}')]
    public function update(UpdateBlogPostRequest $request, BlogPost $blog_post): JsonResponse
    {
        $this->authorize('update', $blog_post);

        return $this->updated(
            new BlogPostResource($this->blogPostService->update($blog_post, $request->validated())),
            'Blog post updated successfully.',
        );
    }

    /**
     * Delete a resource.
     *
     * @param  BlogPost  $blog_post
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Deleted blog post.', type: 'array{success: true, message: string, data: null, meta: null, errors: null}')]
    public function destroy(BlogPost $blog_post): JsonResponse
    {
        $this->authorize('delete', $blog_post);
        $this->blogPostService->destroy($blog_post);

        return $this->deleted('Blog post deleted successfully.');
    }

    /**
     * Store featured image.
     *
     * @param  StoreFeaturedImageRequest  $request
     * @param  BlogPost  $blog_post
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Blog post with featured image.', type: 'array{success: true, message: string, data: BlogPostResource, meta: null, errors: null}')]
    public function storeFeaturedImage(StoreFeaturedImageRequest $request, BlogPost $blog_post): JsonResponse
    {
        $this->authorize('update', $blog_post);

        return $this->updated(
            new BlogPostResource($this->blogPostService->storeFeaturedImage($blog_post, $request->file('featured_image'))),
            'Featured image uploaded successfully.',
        );
    }

    /**
     * Destroy featured image.
     *
     * @param  BlogPost  $blog_post
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Blog post with featured image removed.', type: 'array{success: true, message: string, data: BlogPostResource, meta: null, errors: null}')]
    public function destroyFeaturedImage(BlogPost $blog_post): JsonResponse
    {
        $this->authorize('update', $blog_post);

        return $this->updated(
            new BlogPostResource($this->blogPostService->destroyFeaturedImage($blog_post)),
            'Featured image deleted successfully.',
        );
    }
}
