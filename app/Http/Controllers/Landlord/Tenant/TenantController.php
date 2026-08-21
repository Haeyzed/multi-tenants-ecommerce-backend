<?php

declare(strict_types=1);

namespace App\Http\Controllers\Landlord\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Landlord\Tenant\IndexTenantRequest;
use App\Http\Requests\Landlord\Tenant\StoreTenantRequest;
use App\Http\Requests\Landlord\Tenant\UpdateTenantRequest;
use App\Http\Resources\Landlord\Tenant\TenantResource;
use App\Models\Landlord\Tenant;
use App\Services\Landlord\Tenant\TenantService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

/**
 * Landlord tenant management endpoints.
 */
class TenantController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @param  TenantService  $tenantService
     */
    public function __construct(private readonly TenantService $tenantService) {}

    /**
     * List tenants with pagination and filters.
     *
     * @param  IndexTenantRequest  $request
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Paginated list of tenants.',
        type: 'array{success: true, message: string, data: TenantResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}',
    )]
    public function index(IndexTenantRequest $request): JsonResponse
    {
        $tenants = $this->tenantService->list($request->validated());

        return $this->success(
            TenantResource::collection($tenants->items()),
            'Tenants retrieved successfully.',
            $this->paginationMeta($tenants),
        );
    }

    /**
     * Return tenant options as label/value pairs.
     *
     * @param  IndexTenantRequest  $request
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Tenant options for select inputs.',
        type: ApiResponseSchema::OPTIONS,
    )]
    public function options(IndexTenantRequest $request): JsonResponse
    {
        return $this->success(
            $this->tenantService->options($request->validated()),
            'Tenant options retrieved successfully.',
        );
    }

    /**
     * Create and provision a tenant.
     *
     * @param  StoreTenantRequest  $request
     * @return JsonResponse
     */
    #[Response(
        status: 201,
        description: 'Created tenant.',
        type: 'array{success: true, message: string, data: TenantResource, meta: null, errors: null}',
    )]
    public function store(StoreTenantRequest $request): JsonResponse
    {
        $tenant = $this->tenantService->store($request->validated());

        return $this->created(
            new TenantResource($tenant),
            'Tenant created successfully.',
        );
    }

    /**
     * Show a tenant.
     *
     * @param  Tenant  $tenant
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'A single tenant.',
        type: 'array{success: true, message: string, data: TenantResource, meta: null, errors: null}',
    )]
    public function show(Tenant $tenant): JsonResponse
    {
        return $this->success(
            new TenantResource($this->tenantService->show($tenant)),
            'Tenant retrieved successfully.',
        );
    }

    /**
     * Update a tenant.
     *
     * @param  UpdateTenantRequest  $request
     * @param  Tenant  $tenant
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Updated tenant.',
        type: 'array{success: true, message: string, data: TenantResource, meta: null, errors: null}',
    )]
    public function update(UpdateTenantRequest $request, Tenant $tenant): JsonResponse
    {
        $tenant = $this->tenantService->update($tenant, $request->validated());

        return $this->updated(
            new TenantResource($tenant),
            'Tenant updated successfully.',
        );
    }

    /**
     * Delete a tenant.
     *
     * @param  Tenant  $tenant
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Tenant deleted.',
        type: 'array{success: true, message: string, data: null, meta: null, errors: null}',
    )]
    public function destroy(Tenant $tenant): JsonResponse
    {
        $this->tenantService->destroy($tenant);

        return $this->deleted('Tenant deleted successfully.');
    }
}
