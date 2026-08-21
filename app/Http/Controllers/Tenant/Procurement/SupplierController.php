<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Procurement\StoreSupplierRequest;
use App\Http\Requests\Tenant\Procurement\UpdateSupplierRequest;
use App\Http\Resources\Tenant\Procurement\SupplierResource;
use App\Models\Tenant\Supplier;
use App\Services\Tenant\Procurement\SupplierService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin supplier CRUD.
 */
class SupplierController extends Controller
{
    /**
     * Create a new class instance.
     *
     * @param  SupplierService  $supplierService
     */
    public function __construct(private readonly SupplierService $supplierService) {}

    /**
     * List resources with pagination and filters.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Paginated suppliers.', type: 'array{success: true, message: string, data: SupplierResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}')]
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Supplier::class);

        $suppliers = $this->supplierService->list($request->only(['search', 'status', 'per_page']));

        return $this->success(
            SupplierResource::collection($suppliers->items()),
            'Suppliers retrieved successfully.',
            $this->paginationMeta($suppliers),
        );
    }

    /**
     * Return options for select inputs.
     *
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Supplier options.', type: ApiResponseSchema::OPTIONS)]
    public function options(): JsonResponse
    {
        $this->authorize('viewAny', Supplier::class);

        return $this->success(
            $this->supplierService->options(),
            'Supplier options retrieved successfully.',
        );
    }

    /**
     * Create a resource.
     *
     * @param  StoreSupplierRequest  $request
     * @return JsonResponse
     */
    #[Response(status: 201, description: 'Created supplier.', type: 'array{success: true, message: string, data: SupplierResource, meta: null, errors: null}')]
    public function store(StoreSupplierRequest $request): JsonResponse
    {
        $this->authorize('create', Supplier::class);

        return $this->created(
            new SupplierResource($this->supplierService->store($request->validated())),
            'Supplier created successfully.',
        );
    }

    /**
     * Retrieve a single resource.
     *
     * @param  Supplier  $supplier
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'A supplier.', type: 'array{success: true, message: string, data: SupplierResource, meta: null, errors: null}')]
    public function show(Supplier $supplier): JsonResponse
    {
        $this->authorize('view', $supplier);

        return $this->success(
            new SupplierResource($this->supplierService->show($supplier)),
            'Supplier retrieved successfully.',
        );
    }

    /**
     * Update a resource.
     *
     * @param  UpdateSupplierRequest  $request
     * @param  Supplier  $supplier
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Updated supplier.', type: 'array{success: true, message: string, data: SupplierResource, meta: null, errors: null}')]
    public function update(UpdateSupplierRequest $request, Supplier $supplier): JsonResponse
    {
        $this->authorize('update', $supplier);

        return $this->updated(
            new SupplierResource($this->supplierService->update($supplier, $request->validated())),
            'Supplier updated successfully.',
        );
    }

    /**
     * Delete a resource.
     *
     * @param  Supplier  $supplier
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Deleted supplier.', type: 'array{success: true, message: string, data: null, meta: null, errors: null}')]
    public function destroy(Supplier $supplier): JsonResponse
    {
        $this->authorize('delete', $supplier);
        $this->supplierService->destroy($supplier);

        return $this->deleted('Supplier deleted successfully.');
    }
}
