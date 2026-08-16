<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Commerce;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Commerce\StoreFlashSaleItemRequest;
use App\Http\Requests\Tenant\Commerce\StoreFlashSaleRequest;
use App\Http\Requests\Tenant\Commerce\UpdateFlashSaleItemRequest;
use App\Http\Requests\Tenant\Commerce\UpdateFlashSaleRequest;
use App\Http\Resources\Tenant\Commerce\FlashSaleItemResource;
use App\Http\Resources\Tenant\Commerce\FlashSaleResource;
use App\Models\Tenant\FlashSale;
use App\Models\Tenant\FlashSaleItem;
use App\Services\Tenant\Commerce\FlashSaleService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Admin flash sale CRUD.
 */
class FlashSaleController extends Controller
{
    public function __construct(private readonly FlashSaleService $flashSaleService) {}

    #[Response(status: 200, description: 'Paginated flash sales.', type: 'array{success: true, message: string, data: FlashSaleResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}')]
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', FlashSale::class);

        $flashSales = $this->flashSaleService->list($request->only(['search', 'is_active', 'per_page']));

        return $this->success(
            FlashSaleResource::collection($flashSales->items()),
            'Flash sales retrieved successfully.',
            $this->paginationMeta($flashSales),
        );
    }

    #[Response(status: 201, description: 'Created flash sale.', type: 'array{success: true, message: string, data: FlashSaleResource, meta: null, errors: null}')]
    public function store(StoreFlashSaleRequest $request): JsonResponse
    {
        $this->authorize('create', FlashSale::class);

        return $this->created(
            new FlashSaleResource($this->flashSaleService->store($request->validated())),
            'Flash sale created successfully.',
        );
    }

    #[Response(status: 200, description: 'A flash sale.', type: 'array{success: true, message: string, data: FlashSaleResource, meta: null, errors: null}')]
    public function show(FlashSale $flashSale): JsonResponse
    {
        $this->authorize('view', $flashSale);

        return $this->success(
            new FlashSaleResource($this->flashSaleService->show($flashSale)),
            'Flash sale retrieved successfully.',
        );
    }

    #[Response(status: 200, description: 'Updated flash sale.', type: 'array{success: true, message: string, data: FlashSaleResource, meta: null, errors: null}')]
    public function update(UpdateFlashSaleRequest $request, FlashSale $flashSale): JsonResponse
    {
        $this->authorize('update', $flashSale);

        return $this->updated(
            new FlashSaleResource($this->flashSaleService->update($flashSale, $request->validated())),
            'Flash sale updated successfully.',
        );
    }

    #[Response(status: 200, description: 'Deleted flash sale.', type: 'array{success: true, message: string, data: null, meta: null, errors: null}')]
    public function destroy(FlashSale $flashSale): JsonResponse
    {
        $this->authorize('delete', $flashSale);
        $this->flashSaleService->destroy($flashSale);

        return $this->deleted('Flash sale deleted successfully.');
    }

    #[Response(status: 201, description: 'Created flash sale item.', type: 'array{success: true, message: string, data: FlashSaleItemResource, meta: null, errors: null}')]
    public function storeItem(StoreFlashSaleItemRequest $request, FlashSale $flashSale): JsonResponse
    {
        $this->authorize('update', $flashSale);

        return $this->created(
            new FlashSaleItemResource($this->flashSaleService->addItem($flashSale, $request->validated())),
            'Flash sale item created successfully.',
        );
    }

    #[Response(status: 200, description: 'Updated flash sale item.', type: 'array{success: true, message: string, data: FlashSaleItemResource, meta: null, errors: null}')]
    public function updateItem(
        UpdateFlashSaleItemRequest $request,
        FlashSale $flashSale,
        FlashSaleItem $flashSaleItem,
    ): JsonResponse {
        $this->authorize('update', $flashSale);
        $this->assertItemBelongsToSale($flashSale, $flashSaleItem);

        return $this->updated(
            new FlashSaleItemResource($this->flashSaleService->updateItem($flashSaleItem, $request->validated())),
            'Flash sale item updated successfully.',
        );
    }

    #[Response(status: 200, description: 'Deleted flash sale item.', type: 'array{success: true, message: string, data: null, meta: null, errors: null}')]
    public function destroyItem(FlashSale $flashSale, FlashSaleItem $flashSaleItem): JsonResponse
    {
        $this->authorize('update', $flashSale);
        $this->assertItemBelongsToSale($flashSale, $flashSaleItem);
        $this->flashSaleService->removeItem($flashSaleItem);

        return $this->deleted('Flash sale item deleted successfully.');
    }

    protected function assertItemBelongsToSale(FlashSale $flashSale, FlashSaleItem $item): void
    {
        if ((int) $item->flash_sale_id !== (int) $flashSale->id) {
            throw new NotFoundHttpException('Flash sale item not found for this sale.');
        }
    }
}
