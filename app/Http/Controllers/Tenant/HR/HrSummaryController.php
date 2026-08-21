<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\HR;

use App\Http\Controllers\Controller;
use App\Services\Tenant\HR\HrSummaryService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

/**
 * Lightweight HR reporting totals.
 */
#[Group('HR')]
class HrSummaryController extends Controller
{
    /**
     * Create a new class instance.
     *
     * @param  HrSummaryService  $hrSummaryService
     */
    public function __construct(private readonly HrSummaryService $hrSummaryService) {}

    /**
     * Retrieve a single resource.
     *
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'HR summary totals.', type: 'array{success: true, message: string, data: array<string, mixed>, meta: null, errors: null}')]
    public function show(): JsonResponse
    {
        $this->authorize('viewHrSummary');

        return $this->success(
            $this->hrSummaryService->summary(),
            'HR summary retrieved successfully.',
        );
    }
}
