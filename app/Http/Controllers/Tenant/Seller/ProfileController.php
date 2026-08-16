<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Seller\Auth\StoreLogoRequest;
use App\Http\Requests\Tenant\Seller\Auth\UpdateProfileRequest;
use App\Http\Resources\Media\MediaResource;
use App\Http\Resources\Tenant\Marketplace\SellerResource;
use App\Models\Tenant\Seller;
use App\Services\Tenant\Seller\SellerAuthService;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Authenticated seller profile and logo endpoints.
 */
class ProfileController extends Controller
{
    public function __construct(
        private readonly SellerAuthService $authService,
    ) {}

    /**
     * Return the authenticated seller.
     */
    #[Response(
        status: 200,
        description: 'Authenticated seller profile.',
        type: 'array{success: true, message: string, data: SellerResource, meta: null, errors: null}',
    )]
    public function me(Request $request): JsonResponse
    {
        /** @var Seller $seller */
        $seller = $request->user('seller') ?? $request->user('sanctum') ?? $request->user();

        abort_unless($seller instanceof Seller, 401);

        return $this->success(
            new SellerResource($seller->load('sellerGroup')),
            'Profile retrieved successfully.',
        );
    }

    /**
     * Update the authenticated seller's profile.
     */
    #[Response(
        status: 200,
        description: 'Updated seller profile.',
        type: 'array{success: true, message: string, data: SellerResource, meta: null, errors: null}',
    )]
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        /** @var Seller $seller */
        $seller = $request->user('seller') ?? $request->user('sanctum') ?? $request->user();

        abort_unless($seller instanceof Seller, 401);

        $seller = $this->authService->updateProfile($seller, $request->validated());

        return $this->updated(
            new SellerResource($seller->load('sellerGroup')),
            'Profile updated successfully.',
        );
    }

    /**
     * Upload or replace the authenticated seller's logo.
     */
    #[Response(
        status: 200,
        description: 'Uploaded logo media.',
        type: 'array{success: true, message: string, data: MediaResource, meta: null, errors: null}',
    )]
    public function storeLogo(StoreLogoRequest $request): JsonResponse
    {
        /** @var Seller $seller */
        $seller = $request->user('seller') ?? $request->user('sanctum') ?? $request->user();

        abort_unless($seller instanceof Seller, 401);

        $media = $this->authService->replaceLogo($seller, $request->file('logo'));

        return $this->updated(
            new MediaResource($media),
            'Logo uploaded successfully.',
        );
    }

    /**
     * Delete the authenticated seller's logo.
     */
    #[Response(
        status: 200,
        description: 'Logo deleted.',
        type: 'array{success: true, message: string, data: null, meta: null, errors: null}',
    )]
    public function destroyLogo(Request $request): JsonResponse
    {
        /** @var Seller $seller */
        $seller = $request->user('seller') ?? $request->user('sanctum') ?? $request->user();

        abort_unless($seller instanceof Seller, 401);

        $this->authService->removeLogo($seller);

        return $this->deleted('Logo deleted successfully.');
    }
}
