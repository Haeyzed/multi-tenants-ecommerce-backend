<?php

declare(strict_types=1);

namespace App\Http\Controllers\Landlord\TenantProfile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Landlord\TenantProfile\StoreTenantProfileRequest;
use App\Http\Requests\Landlord\TenantProfile\UpdateTenantProfileRequest;
use App\Http\Resources\Landlord\TenantProfile\TenantProfileResource;
use App\Models\Landlord\Tenant;
use App\Services\Landlord\TenantProfile\TenantProfileService;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

/**
 * Landlord tenant profile endpoints nested under a tenant.
 */
class TenantProfileController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(private readonly TenantProfileService $tenantProfileService) {}

    /**
     * Show the profile for a tenant.
     */
    #[Response(
        status: 200,
        description: 'Tenant profile.',
        type: 'array{success: true, message: string, data: TenantProfileResource, meta: null, errors: null}',
    )]
    public function show(Tenant $tenant): JsonResponse
    {
        return $this->success(
            new TenantProfileResource($this->tenantProfileService->showForTenant($tenant)),
            'Tenant profile retrieved successfully.',
        );
    }

    /**
     * Create a profile for a tenant.
     */
    #[Response(
        status: 201,
        description: 'Created tenant profile.',
        type: 'array{success: true, message: string, data: TenantProfileResource, meta: null, errors: null}',
    )]
    public function store(StoreTenantProfileRequest $request, Tenant $tenant): JsonResponse
    {
        $data = $request->safe()->except(['logo', 'banner']);

        $profile = $this->tenantProfileService->store(
            $tenant,
            $data,
            $request->file('logo'),
            $request->file('banner'),
        );

        return $this->created(
            new TenantProfileResource($profile),
            'Tenant profile created successfully.',
        );
    }

    /**
     * Update a tenant profile.
     */
    #[Response(
        status: 200,
        description: 'Updated tenant profile.',
        type: 'array{success: true, message: string, data: TenantProfileResource, meta: null, errors: null}',
    )]
    public function update(UpdateTenantProfileRequest $request, Tenant $tenant): JsonResponse
    {
        $data = $request->safe()->except(['logo', 'banner']);

        $profile = $this->tenantProfileService->update(
            $tenant,
            $data,
            $request->file('logo'),
            $request->file('banner'),
        );

        return $this->updated(
            new TenantProfileResource($profile),
            'Tenant profile updated successfully.',
        );
    }

    /**
     * Delete a tenant profile.
     */
    #[Response(
        status: 200,
        description: 'Deleted tenant profile confirmation.',
        type: 'array{success: true, message: string, data: null, meta: null, errors: null}',
    )]
    public function destroy(Tenant $tenant): JsonResponse
    {
        $this->tenantProfileService->destroy($tenant);

        return $this->deleted('Tenant profile deleted successfully.');
    }
}
