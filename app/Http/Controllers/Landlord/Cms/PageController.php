<?php

declare(strict_types=1);

namespace App\Http\Controllers\Landlord\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Landlord\Cms\StorePageRequest;
use App\Http\Requests\Landlord\Cms\UpdatePageRequest;
use App\Http\Resources\Landlord\Cms\PageResource;
use App\Models\Landlord\Cms\Page;
use App\Services\Landlord\Cms\PageService;
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
        return $this->created(
            new PageResource($this->pageService->store($request->validated())),
            'Page created successfully.',
        );
    }

    #[Response(status: 200, description: 'A page.', type: 'array{success: true, message: string, data: PageResource, meta: null, errors: null}')]
    public function show(Page $page): JsonResponse
    {
        return $this->success(
            new PageResource($this->pageService->show($page)),
            'Page retrieved successfully.',
        );
    }

    #[Response(status: 200, description: 'Updated page.', type: 'array{success: true, message: string, data: PageResource, meta: null, errors: null}')]
    public function update(UpdatePageRequest $request, Page $page): JsonResponse
    {
        return $this->updated(
            new PageResource($this->pageService->update($page, $request->validated())),
            'Page updated successfully.',
        );
    }

    #[Response(status: 200, description: 'Deleted page.', type: 'array{success: true, message: string, data: null, meta: null, errors: null}')]
    public function destroy(Page $page): JsonResponse
    {
        $this->pageService->destroy($page);

        return $this->deleted('Page deleted successfully.');
    }
}
