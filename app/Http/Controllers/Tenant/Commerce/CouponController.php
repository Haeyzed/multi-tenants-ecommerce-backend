<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Commerce;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Commerce\StoreCouponRequest;
use App\Http\Requests\Tenant\Commerce\UpdateCouponRequest;
use App\Http\Resources\Tenant\Commerce\CouponResource;
use App\Models\Tenant\Coupon;
use App\Services\Tenant\Commerce\CouponService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin coupon CRUD.
 */
class CouponController extends Controller
{
    public function __construct(private readonly CouponService $couponService) {}

    #[Response(status: 200, description: 'Paginated coupons.', type: 'array{success: true, message: string, data: CouponResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}')]
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Coupon::class);

        $coupons = $this->couponService->list($request->only(['search', 'is_active', 'per_page']));

        return $this->success(
            CouponResource::collection($coupons->items()),
            'Coupons retrieved successfully.',
            $this->paginationMeta($coupons),
        );
    }

    #[Response(status: 201, description: 'Created coupon.', type: 'array{success: true, message: string, data: CouponResource, meta: null, errors: null}')]
    public function store(StoreCouponRequest $request): JsonResponse
    {
        $this->authorize('create', Coupon::class);

        return $this->created(
            new CouponResource($this->couponService->store($request->validated())),
            'Coupon created successfully.',
        );
    }

    #[Response(status: 200, description: 'A coupon.', type: 'array{success: true, message: string, data: CouponResource, meta: null, errors: null}')]
    public function show(Coupon $coupon): JsonResponse
    {
        $this->authorize('view', $coupon);

        return $this->success(
            new CouponResource($this->couponService->show($coupon)),
            'Coupon retrieved successfully.',
        );
    }

    #[Response(status: 200, description: 'Updated coupon.', type: 'array{success: true, message: string, data: CouponResource, meta: null, errors: null}')]
    public function update(UpdateCouponRequest $request, Coupon $coupon): JsonResponse
    {
        $this->authorize('update', $coupon);

        return $this->updated(
            new CouponResource($this->couponService->update($coupon, $request->validated())),
            'Coupon updated successfully.',
        );
    }

    #[Response(status: 200, description: 'Deleted coupon.', type: 'array{success: true, message: string, data: null, meta: null, errors: null}')]
    public function destroy(Coupon $coupon): JsonResponse
    {
        $this->authorize('delete', $coupon);
        $this->couponService->destroy($coupon);

        return $this->deleted('Coupon deleted successfully.');
    }
}
