<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Seller\Auth\ChangePasswordRequest;
use App\Http\Requests\Tenant\Seller\Auth\ForgotPasswordRequest;
use App\Http\Requests\Tenant\Seller\Auth\LoginRequest;
use App\Http\Requests\Tenant\Seller\Auth\RegisterRequest;
use App\Http\Requests\Tenant\Seller\Auth\ResetPasswordRequest;
use App\Http\Resources\Tenant\Marketplace\SellerResource;
use App\Models\Tenant\Seller;
use App\Services\Tenant\Seller\SellerAuthService;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Seller authentication endpoints (profile lives on ProfileController).
 */
class AuthController extends Controller
{
    /**
     * Create a new class instance.
     *
     * @param  SellerAuthService  $authService
     */
    public function __construct(
        private readonly SellerAuthService $authService,
    ) {}

    /**
     * Register a new seller account when registration is enabled.
     *
     * @param  RegisterRequest  $request
     * @return JsonResponse
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
     *
     * @param  LoginRequest  $request
     * @return JsonResponse
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
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Logout confirmation.',
        type: 'array{success: true, message: string, data: null, meta: null, errors: null}',
    )]
    public function logout(Request $request): JsonResponse
    {
        /** @var Seller $seller */
        $seller = $request->user('seller') ?? $request->user('sanctum') ?? $request->user();

        abort_unless($seller instanceof Seller, 401);

        $this->authService->logout($seller);

        return $this->success(null, 'Logged out successfully.');
    }

    /**
     * Request a password reset link for a seller.
     *
     * @param  ForgotPasswordRequest  $request
     * @return JsonResponse
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
     *
     * @param  ResetPasswordRequest  $request
     * @return JsonResponse
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
     * Change the authenticated seller's password.
     *
     * @param  ChangePasswordRequest  $request
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Password change confirmation.',
        type: 'array{success: true, message: string, data: null, meta: null, errors: null}',
    )]
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        /** @var Seller $seller */
        $seller = $request->user('seller') ?? $request->user('sanctum') ?? $request->user();

        abort_unless($seller instanceof Seller, 401);

        $this->authService->changePassword($seller, $request->validated());

        return $this->success(null, 'Password changed successfully.');
    }
}
