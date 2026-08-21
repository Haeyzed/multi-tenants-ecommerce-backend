<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Inventory;

use App\Enums\Tenant\Catalog\InventoryMovementType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Inventory\AdjustInventoryRequest;
use App\Http\Requests\Tenant\Inventory\IndexInventoryRequest;
use App\Http\Requests\Tenant\Inventory\ReleaseInventoryRequest;
use App\Http\Requests\Tenant\Inventory\ReserveInventoryRequest;
use App\Http\Requests\Tenant\Inventory\StoreInventoryRequest;
use App\Http\Requests\Tenant\Inventory\TransferInventoryRequest;
use App\Http\Resources\Tenant\Inventory\InventoryResource;
use App\Models\Tenant\Inventory;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductVariant;
use App\Models\Tenant\User;
use App\Models\Tenant\Warehouse;
use App\Services\Tenant\Inventory\InventoryService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

/**
 * Tenant inventory stock endpoints.
 */
class InventoryController extends Controller
{
    /**
     * Create a new class instance.
     *
     * @param  InventoryService  $inventoryService
     */
    public function __construct(private readonly InventoryService $inventoryService) {}

    /**
     * List inventory records with pagination and filters.
     *
     * @param  IndexInventoryRequest  $request
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Paginated list of inventory records.',
        type: 'array{success: true, message: string, data: InventoryResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}',
    )]
    public function index(IndexInventoryRequest $request): JsonResponse
    {
        $inventories = $this->inventoryService->list($request->validated());

        return $this->success(
            InventoryResource::collection($inventories->items()),
            'Inventory records retrieved successfully.',
            $this->paginationMeta($inventories),
        );
    }

    /**
     * List inventory records for a warehouse.
     *
     * @param  IndexInventoryRequest  $request
     * @param  Warehouse  $warehouse
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Paginated inventory records for a warehouse.',
        type: 'array{success: true, message: string, data: InventoryResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}',
    )]
    public function indexForWarehouse(IndexInventoryRequest $request, Warehouse $warehouse): JsonResponse
    {
        $params = $request->validated();
        $params['warehouse_id'] = $warehouse->id;
        $inventories = $this->inventoryService->list($params);

        return $this->success(
            InventoryResource::collection($inventories->items()),
            'Warehouse inventory retrieved successfully.',
            $this->paginationMeta($inventories),
        );
    }

    /**
     * Assign a product or variant to a warehouse.
     *
     * @param  StoreInventoryRequest  $request
     * @return JsonResponse
     */
    #[Response(
        status: 201,
        description: 'Created or existing inventory assignment.',
        type: 'array{success: true, message: string, data: InventoryResource, meta: null, errors: null}',
    )]
    public function store(StoreInventoryRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $product = Product::query()->findOrFail((int) $validated['product_id']);
        $variant = isset($validated['product_variant_id'])
            ? ProductVariant::query()->find((int) $validated['product_variant_id'])
            : null;
        $warehouse = Warehouse::query()->findOrFail((int) $validated['warehouse_id']);

        $inventory = $this->inventoryService->assign(
            $warehouse,
            $product,
            $variant,
            $validated,
            $this->actor(),
        );

        return $this->created(
            new InventoryResource($inventory),
            'Inventory assigned successfully.',
        );
    }

    /**
     * Show an inventory record.
     *
     * @param  Inventory  $inventory
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'A single inventory record.',
        type: 'array{success: true, message: string, data: InventoryResource, meta: null, errors: null}',
    )]
    public function show(Inventory $inventory): JsonResponse
    {
        return $this->success(
            new InventoryResource($this->inventoryService->show($inventory)),
            'Inventory record retrieved successfully.',
        );
    }

    /**
     * Adjust inventory quantity.
     *
     * @param  AdjustInventoryRequest  $request
     * @param  Inventory  $inventory
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Adjusted inventory record.',
        type: 'array{success: true, message: string, data: InventoryResource, meta: null, errors: null}',
    )]
    public function adjust(AdjustInventoryRequest $request, Inventory $inventory): JsonResponse
    {
        $validated = $request->validated();

        $inventory = $this->inventoryService->adjust(
            $inventory,
            (int) $validated['quantity'],
            InventoryMovementType::from($validated['type']),
            $validated['reason'] ?? null,
            $validated['notes'] ?? null,
            $this->actor(),
        );

        return $this->updated(
            new InventoryResource($inventory),
            'Inventory adjusted successfully.',
        );
    }

    /**
     * Reserve inventory quantity.
     *
     * @param  ReserveInventoryRequest  $request
     * @param  Inventory  $inventory
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Inventory with reserved quantity updated.',
        type: 'array{success: true, message: string, data: InventoryResource, meta: null, errors: null}',
    )]
    public function reserve(ReserveInventoryRequest $request, Inventory $inventory): JsonResponse
    {
        return $this->updated(
            new InventoryResource($this->inventoryService->reserve($inventory, (int) $request->validated('quantity'))),
            'Inventory reserved successfully.',
        );
    }

    /**
     * Release reserved inventory quantity.
     *
     * @param  ReleaseInventoryRequest  $request
     * @param  Inventory  $inventory
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Inventory with reserved quantity released.',
        type: 'array{success: true, message: string, data: InventoryResource, meta: null, errors: null}',
    )]
    public function release(ReleaseInventoryRequest $request, Inventory $inventory): JsonResponse
    {
        return $this->updated(
            new InventoryResource($this->inventoryService->release($inventory, (int) $request->validated('quantity'))),
            'Inventory released successfully.',
        );
    }

    /**
     * Transfer inventory between warehouses.
     *
     * @param  TransferInventoryRequest  $request
     * @param  Inventory  $inventory
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Inventory transfer result.',
        type: 'array{success: true, message: string, data: array{from: InventoryResource, to: InventoryResource}, meta: null, errors: null}',
    )]
    public function transfer(TransferInventoryRequest $request, Inventory $inventory): JsonResponse
    {
        $validated = $request->validated();
        $toWarehouse = Warehouse::query()->findOrFail($validated['to_warehouse_id']);

        $result = $this->inventoryService->transfer(
            $inventory,
            $toWarehouse,
            (int) $validated['quantity'],
            $this->actor(),
        );

        return $this->updated(
            [
                'from' => new InventoryResource($result['from']),
                'to' => new InventoryResource($result['to']),
            ],
            'Inventory transferred successfully.',
        );
    }

    /**
     * Remove an empty inventory assignment.
     *
     * @param  Inventory  $inventory
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Inventory assignment removed.',
        type: 'array{success: true, message: string, data: null, meta: null, errors: null}',
    )]
    public function destroy(Inventory $inventory): JsonResponse
    {
        $this->inventoryService->unassign($inventory);

        return $this->deleted('Inventory assignment removed successfully.');
    }

    /**
     * Resolve the authenticated tenant user.
     *
     * @return ?User
     */
    protected function actor(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }
}
