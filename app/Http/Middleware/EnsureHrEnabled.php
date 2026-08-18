<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Tenant\HR\HrSettingsService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Block operational HR routes when the tenant has disabled the HR module.
 */
class EnsureHrEnabled
{
    public function __construct(private readonly HrSettingsService $hrSettings) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->hrSettings->isEnabled()) {
            return response()->json([
                'success' => false,
                'message' => 'HR is not enabled for this tenant.',
                'data' => null,
                'meta' => null,
                'errors' => null,
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
