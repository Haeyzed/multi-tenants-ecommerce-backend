<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Landlord\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensure the current tenant has an active or trialing subscription.
 */
class EnsureActiveSubscription
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
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

        $subscription = $tenant->activeSubscription();

        if ($subscription === null || ! $subscription->grantsAccess()) {
            return response()->json([
                'success' => false,
                'message' => 'An active subscription is required.',
                'data' => null,
                'meta' => null,
                'errors' => null,
            ], Response::HTTP_PAYMENT_REQUIRED);
        }

        return $next($request);
    }
}
