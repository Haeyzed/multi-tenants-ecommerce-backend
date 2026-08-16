<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Cms\StorePageRequest;
use App\Http\Requests\Tenant\Cms\UpdatePageRequest;
use App\Http\Resources\Tenant\Cms\PageResource;
use App\Models\Tenant\Cms\Page;
use App\Services\Tenant\Cms\PageService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PageController extends Controller
{
    public function __construct(private readonly PageService $pageService) {}

    #[Response(status: 200, description: 'Paginated pages.', type: 'array{success: true, message: string, data: PageResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}')]
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Page::class);

        $pages = $this->pageService->list($request->only(['search', 'status', 'per_page']));

        return $this->success(
            PageResource::collection($pages->items()),
            'Pages retrieved successfully.',
            $this->paginationMeta($pages),
        );
    }

    #[Response(status: 201, description: 'Created page.', type: 'array{success: true, message: string, data: PageResource, meta: null, errors: null}')]
    public function store(StorePageRequest $request): JsonResponse
    {
        $this->authorize('create', Page::class);

        return $this->created(
            new PageResource($this->pageService->store($request->validated())),
            'Page created successfully.',
        );
    }

    #[Response(status: 200, description: 'A page.', type: 'array{success: true, message: string, data: PageResource, meta: null, errors: null}')]
    public function show(Page $page): JsonResponse
    {
        $this->authorize('view', $page);

        return $this->success(
            new PageResource($this->pageService->show($page)),
            'Page retrieved successfully.',
        );
    }

    #[Response(status: 200, description: 'Updated page.', type: 'array{success: true, message: string, data: PageResource, meta: null, errors: null}')]
    public function update(UpdatePageRequest $request, Page $page): JsonResponse
    {
        $this->authorize('update', $page);

        return $this->updated(
            new PageResource($this->pageService->update($page, $request->validated())),
            'Page updated successfully.',
        );
    }

    #[Response(status: 200, description: 'Deleted page.', type: 'array{success: true, message: string, data: null, meta: null, errors: null}')]
    public function destroy(Page $page): JsonResponse
    {
        $this->authorize('delete', $page);
        $this->pageService->destroy($page);

        return $this->deleted('Page deleted successfully.');
    }
}
