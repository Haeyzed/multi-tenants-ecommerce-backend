<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Landlord\Tenant;
use App\Services\Tenant\HR\HrSettingsService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate public recruitment routes on tenant HR settings (includes plan-aware checks).
 */
class EnsurePublicRecruitmentEnabled
{
    public function __construct(private readonly HrSettingsService $hrSettings) {}

    /**
     * @param  Closure(Request): Response  $next
     * @param  'listings'|'apply'|'offers'  $scope
     */
    public function handle(Request $request, Closure $next, string $scope = 'listings'): Response
    {
        if (! tenant() instanceof Tenant) {
            return $this->forbidden('Tenant context is required.');
        }

        try {
            match ($scope) {
                'listings' => $this->hrSettings->assertPublicJobListingsEnabled(),
                'apply' => $this->hrSettings->assertPublicJobApplicationsEnabled(),
                'offers' => $this->hrSettings->assertRecruitmentEnabled(),
                default => $this->hrSettings->assertRecruitmentEnabled(),
            };
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first();

            return $this->forbidden(is_string($message) ? $message : 'Recruitment is not available.');
        }

        return $next($request);
    }

    protected function forbidden(string $message): Response
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => null,
            'meta' => null,
            'errors' => null,
        ], Response::HTTP_FORBIDDEN);
    }
}
