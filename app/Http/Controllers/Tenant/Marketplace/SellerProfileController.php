<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Marketplace;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Marketplace\UpdateSellerRequest;
use App\Http\Resources\Tenant\Marketplace\SellerResource;
use App\Models\Tenant\Seller;
use App\Models\Tenant\User;
use App\Services\Tenant\Marketplace\SellerService;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Authenticated seller staff profile endpoints.
 */
class SellerProfileController extends Controller
{
    public function __construct(private readonly SellerService $sellerService) {}

    #[Response(status: 200, description: 'Seller profile.', type: 'array{success: true, message: string, data: SellerResource, meta: null, errors: null}')]
    public function show(): JsonResponse
    {
        $seller = $this->resolveSeller();
        $this->authorize('view', $seller);

        return $this->success(
            new SellerResource($this->sellerService->show($seller)),
            'Seller profile retrieved successfully.',
        );
    }

    #[Response(status: 200, description: 'Updated seller profile.', type: 'array{success: true, message: string, data: SellerResource, meta: null, errors: null}')]
    public function update(UpdateSellerRequest $request): JsonResponse
    {
        $seller = $this->resolveSeller();
        $this->authorize('update', $seller);

        $data = collect($request->validated())
            ->except(['commission_type', 'commission_rate', 'commission_fixed_amount'])
            ->all();

        return $this->updated(
            new SellerResource($this->sellerService->update($seller, $data)),
            'Seller profile updated successfully.',
        );
    }

    /**
     * Resolve the seller bound to the authenticated tenant user.
     */
    protected function resolveSeller(): Seller
    {
        /** @var User $user */
        $user = auth('tenant')->user();

        if ($user->seller_id === null) {
            throw new AccessDeniedHttpException('This account is not linked to a seller.');
        }

        return Seller::query()->findOrFail($user->seller_id);
    }
}
