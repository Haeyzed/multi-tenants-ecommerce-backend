<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Tenant root health/info endpoint (route:cache friendly).
 */
class HomeController extends Controller
{
    /**
     * Confirm the tenant application is reachable without leaking the tenant id.
     *
     * @return JsonResponse
     */
    public function __invoke(): JsonResponse
    {
        return $this->success([
            'application' => 'tenant',
            'status' => 'ok',
        ], 'Tenant application is available.');
    }
}
