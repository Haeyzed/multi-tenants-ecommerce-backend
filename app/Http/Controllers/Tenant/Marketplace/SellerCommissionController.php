<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Marketplace;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Marketplace\IndexSellerCommissionRequest;
use App\Http\Resources\Tenant\Marketplace\SellerCommissionResource;
use App\Models\Tenant\SellerCommission;
use App\Services\Tenant\Marketplace\CommissionService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

/**
 * Marketplace commission listing and detail.
 */
class SellerCommissionController extends Controller
{
    /**
     * Create a new class instance.
     *
     * @param  CommissionService  $commissions
     */
    public function __construct(private readonly CommissionService $commissions) {}

    /**
     * List resources with pagination and filters.
     *
     * @param  IndexSellerCommissionRequest  $request
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Paginated commissions.', type: 'array{success: true, message: string, data: SellerCommissionResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}')]
    public function index(IndexSellerCommissionRequest $request): JsonResponse
    {
        $this->authorize('viewAny', SellerCommission::class);

        $commissions = $this->commissions->list($request->validated(), $request->user());

        return $this->success(
            SellerCommissionResource::collection($commissions->items()),
            'Commissions retrieved successfully.',
            $this->paginationMeta($commissions),
        );
    }

    /**
     * Retrieve a single resource.
     *
     * @param  SellerCommission  $commission
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'A commission.', type: 'array{success: true, message: string, data: SellerCommissionResource, meta: null, errors: null}')]
    public function show(SellerCommission $commission): JsonResponse
    {
        $this->authorize('view', $commission);

        return $this->success(
            new SellerCommissionResource($this->commissions->show($commission)),
            'Commission retrieved successfully.',
        );
    }
}
