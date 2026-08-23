<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\Tenant\ResolvePublicTenantRequest;
use App\Http\Resources\Public\Tenant\PublicTenantResource;
use App\Services\Landlord\Tenant\PublicTenantResolver;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

/**
 * Public tenant discovery by domain/host for auth branding.
 */
class PublicTenantController extends Controller
{
    public function __construct(private readonly PublicTenantResolver $resolver) {}

    /**
     * Resolve a tenant from domain query or request host.
     */
    #[Response(
        status: 200,
        description: 'Public tenant bootstrap profile.',
        type: 'array{success: true, message: string, data: PublicTenantResource, meta: null, errors: null}',
    )]
    public function show(ResolvePublicTenantRequest $request): JsonResponse
    {
        $tenant = $this->resolver->resolveByDomain($request->resolvedDomain());

        return $this->success(
            new PublicTenantResource($tenant),
            'Tenant resolved successfully.',
        );
    }
}
