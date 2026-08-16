<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Require the authenticated tenant user to be linked to a marketplace seller.
 */
class EnsureSellerUser
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('tenant') ?? $request->user();

        if (! $user instanceof User || ! $user->isSellerUser()) {
            return response()->json([
                'success' => false,
                'message' => 'This account is not linked to a seller.',
                'data' => null,
                'meta' => null,
                'errors' => null,
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
