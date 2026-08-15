<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Tenant\Commerce\CommerceSettingService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Block marketplace routes when the tenant has marketplace disabled.
 */
class EnsureMarketplaceEnabled
{
    public function __construct(private readonly CommerceSettingService $commerceSettings) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->commerceSettings->isMarketplaceEnabled()) {
            return response()->json([
                'success' => false,
                'message' => 'Marketplace is not enabled for this tenant.',
                'data' => null,
                'meta' => null,
                'errors' => null,
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
