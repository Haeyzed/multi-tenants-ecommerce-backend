<?php

declare(strict_types=1);

namespace App\Http\Controllers\Landlord\Domain;

use App\Http\Controllers\Controller;
use App\Http\Requests\Landlord\Domain\IndexDomainRequest;
use App\Http\Requests\Landlord\Domain\StoreDomainRequest;
use App\Http\Requests\Landlord\Domain\UpdateDomainRequest;
use App\Http\Resources\Landlord\Domain\DomainResource;
use App\Models\Landlord\Domain;
use App\Models\Landlord\Tenant;
use App\Services\Landlord\Domain\DomainService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

/**
 * Landlord domain management endpoints nested under a tenant.
 */
class DomainController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(private readonly DomainService $domainService) {}

    /**
     * List domains for a tenant.
     */
    #[Response(
        status: 200,
        description: 'Paginated list of domains.',
        type: 'array{success: true, message: string, data: DomainResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}',
    )]
    public function index(IndexDomainRequest $request, Tenant $tenant): JsonResponse
    {
        $domains = $this->domainService->list($tenant, $request->validated());

        return $this->success(
            DomainResource::collection($domains->items()),
            'Domains retrieved successfully.',
            $this->paginationMeta($domains),
        );
    }

    /**
     * Create a domain for a tenant.
     */
    #[Response(
        status: 201,
        description: 'Created domain.',
        type: 'array{success: true, message: string, data: DomainResource, meta: null, errors: null}',
    )]
    public function store(StoreDomainRequest $request, Tenant $tenant): JsonResponse
    {
        $domain = $this->domainService->store($tenant, $request->validated());

        return $this->created(
            new DomainResource($domain),
            'Domain created successfully.',
        );
    }

    /**
     * Show a domain for a tenant.
     */
    #[Response(
        status: 200,
        description: 'A single domain.',
        type: 'array{success: true, message: string, data: DomainResource, meta: null, errors: null}',
    )]
    public function show(Tenant $tenant, Domain $domain): JsonResponse
    {
        return $this->success(
            new DomainResource($this->domainService->show($tenant, $domain)),
            'Domain retrieved successfully.',
        );
    }

    /**
     * Update a domain for a tenant.
     */
    #[Response(
        status: 200,
        description: 'Updated domain.',
        type: 'array{success: true, message: string, data: DomainResource, meta: null, errors: null}',
    )]
    public function update(UpdateDomainRequest $request, Tenant $tenant, Domain $domain): JsonResponse
    {
        $domain = $this->domainService->update($tenant, $domain, $request->validated());

        return $this->updated(
            new DomainResource($domain),
            'Domain updated successfully.',
        );
    }

    /**
     * Delete a domain for a tenant.
     */
    #[Response(
        status: 200,
        description: 'Domain deleted.',
        type: 'array{success: true, message: string, data: null, meta: null, errors: null}',
    )]
    public function destroy(Tenant $tenant, Domain $domain): JsonResponse
    {
        $this->domainService->destroy($tenant, $domain);

        return $this->deleted('Domain deleted successfully.');
    }

    /**
     * Mark a domain as the tenant's primary domain.
     */
    #[Response(
        status: 200,
        description: 'Primary domain updated.',
        type: 'array{success: true, message: string, data: DomainResource, meta: null, errors: null}',
    )]
    public function makePrimary(Tenant $tenant, Domain $domain): JsonResponse
    {
        $domain = $this->domainService->makePrimary($tenant, $domain);

        return $this->updated(
            new DomainResource($domain),
            'Primary domain updated successfully.',
        );
    }
}
