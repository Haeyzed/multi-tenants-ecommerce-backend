<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public\TenantProfile;

use App\Http\Controllers\Controller;
use App\Http\Resources\Public\TenantProfile\TenantProfileResource;
use App\Services\Landlord\TenantProfile\TenantProfileService;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

/**
 * Public tenant profile endpoints.
 */
class TenantProfileController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(private readonly TenantProfileService $tenantProfileService) {}

    /**
     * Show a public tenant profile by slug.
     */
    #[Response(
        status: 200,
        description: 'Public tenant profile.',
        type: 'array{success: true, message: string, data: TenantProfileResource, meta: null, errors: null}',
    )]
    public function show(string $slug): JsonResponse
    {
        return $this->success(
            new TenantProfileResource($this->tenantProfileService->showPublicBySlug($slug)),
            'Tenant profile retrieved successfully.',
        );
    }
}
