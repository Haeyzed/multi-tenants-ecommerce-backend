<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Seller\Auth\ChangePasswordRequest;
use App\Http\Requests\Tenant\Seller\Auth\ForgotPasswordRequest;
use App\Http\Requests\Tenant\Seller\Auth\LoginRequest;
use App\Http\Requests\Tenant\Seller\Auth\RegisterRequest;
use App\Http\Requests\Tenant\Seller\Auth\ResetPasswordRequest;
use App\Http\Requests\Tenant\Seller\Auth\UpdateProfileRequest;
use App\Http\Resources\Tenant\Marketplace\SellerResource;
use App\Models\Tenant\Seller;
use App\Services\Tenant\Seller\SellerAuthService;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Seller authentication and self-service profile endpoints.
 */
class AuthController extends Controller
{
    public function __construct(
        private readonly SellerAuthService $authService,
    ) {}

    /**
     * Register a new seller account when registration is enabled.
     */
    #[Response(
        status: 201,
        description: 'Registered seller with API token.',
        type: 'array{success: true, message: string, data: array{seller: SellerResource, token: string}, meta: null, errors: null}',
    )]
    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->authService->register($request->validated());

        return $this->created([
            'seller' => new SellerResource($result['seller']),
            'token' => $result['token'],
        ], 'Registered successfully.');
    }

    /**
     * Authenticate a seller and return a Sanctum token.
     */
    #[Response(
        status: 200,
        description: 'Authenticated seller with API token.',
        type: 'array{success: true, message: string, data: array{seller: SellerResource, token: string}, meta: null, errors: null}',
    )]
    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login($request->validated());

        return $this->success([
            'seller' => new SellerResource($result['seller']),
            'token' => $result['token'],
        ], 'Logged in successfully.');
    }

    /**
     * Revoke the current seller access token.
     */
    #[Response(
        status: 200,
        description: 'Logout confirmation.',
        type: 'array{success: true, message: string, data: null, meta: null, errors: null}',
    )]
    public function logout(Request $request): JsonResponse
    {
        /** @var Seller $seller */
        $seller = Auth::guard('seller')->user();

        $this->authService->logout($seller);

        return $this->success(null, 'Logged out successfully.');
    }

    /**
     * Request a password reset link for a seller.
     */
    #[Response(
        status: 200,
        description: 'Generic forgot-password acknowledgement.',
        type: 'array{success: true, message: string, data: null, meta: null, errors: null}',
    )]
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $this->authService->forgotPassword($request->validated('email'));

        return $this->success(null, 'If that email address exists, we have sent a password reset link.');
    }

    /**
     * Reset a seller's password using a reset token.
     */
    #[Response(
        status: 200,
        description: 'Password reset confirmation.',
        type: 'array{success: true, message: string, data: null, meta: null, errors: null}',
    )]
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $this->authService->resetPassword($request->validated());

        return $this->success(null, 'Password reset successfully.');
    }

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
        $seller = Auth::guard('seller')->user();

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
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        /** @var Seller $seller */
        $seller = Auth::guard('seller')->user();

        $seller = $this->authService->updateProfile($seller, $request->validated());

        return $this->updated(
            new SellerResource($seller->load('sellerGroup')),
            'Profile updated successfully.',
        );
    }

    /**
     * Change the authenticated seller's password.
     */
    #[Response(
        status: 200,
        description: 'Password change confirmation.',
        type: 'array{success: true, message: string, data: null, meta: null, errors: null}',
    )]
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        /** @var Seller $seller */
        $seller = Auth::guard('seller')->user();

        $this->authService->changePassword($seller, $request->validated());

        return $this->success(null, 'Password changed successfully.');
    }
}
