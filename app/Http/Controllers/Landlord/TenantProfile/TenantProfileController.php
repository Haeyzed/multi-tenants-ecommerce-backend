<?php

declare(strict_types=1);

namespace App\Http\Controllers\Landlord\TenantProfile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Landlord\TenantProfile\StoreCoverRequest;
use App\Http\Requests\Landlord\TenantProfile\StoreLogoRequest;
use App\Http\Requests\Landlord\TenantProfile\StoreTenantProfileRequest;
use App\Http\Requests\Landlord\TenantProfile\UpdateTenantProfileRequest;
use App\Http\Resources\Landlord\TenantProfile\TenantProfileResource;
use App\Http\Resources\Media\MediaResource;
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
     * Create a new class instance.
     *
     * @param  TenantProfileService  $tenantProfileService
     */
    public function __construct(private readonly TenantProfileService $tenantProfileService) {}

    /**
     * Retrieve a single resource.
     *
     * @param  Tenant  $tenant
     * @return JsonResponse
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
     * Create a resource.
     *
     * @param  StoreTenantProfileRequest  $request
     * @param  Tenant  $tenant
     * @return JsonResponse
     */
    #[Response(
        status: 201,
        description: 'Created tenant profile.',
        type: 'array{success: true, message: string, data: TenantProfileResource, meta: null, errors: null}',
    )]
    public function store(StoreTenantProfileRequest $request, Tenant $tenant): JsonResponse
    {
        $data = $request->safe()->except(['logo', 'cover', 'banner']);

        $profile = $this->tenantProfileService->store(
            $tenant,
            $data,
            $request->file('logo'),
            $request->file('cover') ?? $request->file('banner'),
        );

        return $this->created(
            new TenantProfileResource($profile),
            'Tenant profile created successfully.',
        );
    }

    /**
     * Update a resource.
     *
     * @param  UpdateTenantProfileRequest  $request
     * @param  Tenant  $tenant
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Updated tenant profile.',
        type: 'array{success: true, message: string, data: TenantProfileResource, meta: null, errors: null}',
    )]
    public function update(UpdateTenantProfileRequest $request, Tenant $tenant): JsonResponse
    {
        $data = $request->safe()->except(['logo', 'cover', 'banner']);

        $profile = $this->tenantProfileService->update(
            $tenant,
            $data,
            $request->file('logo'),
            $request->file('cover') ?? $request->file('banner'),
        );

        return $this->updated(
            new TenantProfileResource($profile),
            'Tenant profile updated successfully.',
        );
    }

    /**
     * Store logo.
     *
     * @param  StoreLogoRequest  $request
     * @param  Tenant  $tenant
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Uploaded tenant profile logo.',
        type: 'array{success: true, message: string, data: MediaResource, meta: null, errors: null}',
    )]
    public function storeLogo(StoreLogoRequest $request, Tenant $tenant): JsonResponse
    {
        $media = $this->tenantProfileService->replaceLogo($tenant, $request->file('logo'));

        return $this->updated(
            new MediaResource($media),
            'Tenant logo uploaded successfully.',
        );
    }

    /**
     * Destroy logo.
     *
     * @param  Tenant  $tenant
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Deleted tenant profile logo.',
        type: 'array{success: true, message: string, data: null, meta: null, errors: null}',
    )]
    public function destroyLogo(Tenant $tenant): JsonResponse
    {
        $this->tenantProfileService->removeLogo($tenant);

        return $this->deleted('Tenant logo deleted successfully.');
    }

    /**
     * Store cover.
     *
     * @param  StoreCoverRequest  $request
     * @param  Tenant  $tenant
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Uploaded tenant profile cover.',
        type: 'array{success: true, message: string, data: MediaResource, meta: null, errors: null}',
    )]
    public function storeCover(StoreCoverRequest $request, Tenant $tenant): JsonResponse
    {
        $media = $this->tenantProfileService->replaceCover($tenant, $request->file('cover'));

        return $this->updated(
            new MediaResource($media),
            'Tenant cover uploaded successfully.',
        );
    }

    /**
     * Destroy cover.
     *
     * @param  Tenant  $tenant
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Deleted tenant profile cover.',
        type: 'array{success: true, message: string, data: null, meta: null, errors: null}',
    )]
    public function destroyCover(Tenant $tenant): JsonResponse
    {
        $this->tenantProfileService->removeCover($tenant);

        return $this->deleted('Tenant cover deleted successfully.');
    }

    /**
     * Delete a resource.
     *
     * @param  Tenant  $tenant
     * @return JsonResponse
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
