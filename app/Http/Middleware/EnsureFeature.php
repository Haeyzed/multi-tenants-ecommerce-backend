<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Landlord\Tenant;
use App\Services\Landlord\Feature\FeatureAccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensure the current tenant's plan includes the given feature slug.
 */
class EnsureFeature
{
    /**
     * Create a new middleware instance.
     */
    public function __construct(private readonly FeatureAccessService $featureAccessService) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        /** @var Tenant|null $tenant */
        $tenant = tenant();

        if (! $tenant instanceof Tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant context is required.',
                'data' => null,
                'meta' => null,
                'errors' => null,
            ], Response::HTTP_FORBIDDEN);
        }

        if (! $this->featureAccessService->has($tenant, $feature)) {
            return response()->json([
                'success' => false,
                'message' => 'Your current plan does not include this feature.',
                'data' => null,
                'meta' => null,
                'errors' => null,
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
