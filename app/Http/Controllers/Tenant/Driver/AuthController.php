<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Driver;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Driver\Auth\ChangePasswordRequest;
use App\Http\Requests\Tenant\Driver\Auth\ForgotPasswordRequest;
use App\Http\Requests\Tenant\Driver\Auth\LoginRequest;
use App\Http\Requests\Tenant\Driver\Auth\ResetPasswordRequest;
use App\Http\Requests\Tenant\Driver\Auth\UpdateProfileRequest;
use App\Http\Resources\Tenant\Driver\DriverResource;
use App\Models\Tenant\Driver;
use App\Services\Tenant\Driver\DriverAuthService;
use App\Services\Tenant\Driver\DriverService;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Driver authentication and profile endpoints.
 */
class AuthController extends Controller
{
    public function __construct(
        private readonly DriverAuthService $authService,
        private readonly DriverService $driverService,
    ) {}

    /**
     * Authenticate a driver and return a Sanctum token.
     */
    #[Response(
        status: 200,
        description: 'Authenticated driver with API token.',
        type: 'array{success: true, message: string, data: array{driver: DriverResource, token: string}, meta: null, errors: null}',
    )]
    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login($request->validated());

        return $this->success([
            'driver' => new DriverResource($result['driver']),
            'token' => $result['token'],
        ], 'Logged in successfully.');
    }

    /**
     * Revoke the current driver access token.
     */
    #[Response(
        status: 200,
        description: 'Logout confirmation.',
        type: 'array{success: true, message: string, data: null, meta: null, errors: null}',
    )]
    public function logout(Request $request): JsonResponse
    {
        /** @var Driver $driver */
        $driver = Auth::guard('driver')->user();

        $this->authService->logout($driver);

        return $this->success(null, 'Logged out successfully.');
    }

    /**
     * Request a password reset link for a driver.
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
     * Reset a driver's password using a reset token.
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
     * Return the authenticated driver.
     */
    #[Response(
        status: 200,
        description: 'Authenticated driver profile.',
        type: 'array{success: true, message: string, data: DriverResource, meta: null, errors: null}',
    )]
    public function me(Request $request): JsonResponse
    {
        /** @var Driver $driver */
        $driver = Auth::guard('driver')->user();

        return $this->success(
            new DriverResource($driver),
            'Profile retrieved successfully.',
        );
    }

    /**
     * Update the authenticated driver's profile.
     */
    #[Response(
        status: 200,
        description: 'Updated driver profile.',
        type: 'array{success: true, message: string, data: DriverResource, meta: null, errors: null}',
    )]
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        /** @var Driver $driver */
        $driver = Auth::guard('driver')->user();

        $driver = $this->driverService->updateProfile($driver, $request->validated());

        return $this->updated(
            new DriverResource($driver),
            'Profile updated successfully.',
        );
    }

    /**
     * Change the authenticated driver's password.
     */
    #[Response(
        status: 200,
        description: 'Password change confirmation.',
        type: 'array{success: true, message: string, data: null, meta: null, errors: null}',
    )]
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        /** @var Driver $driver */
        $driver = Auth::guard('driver')->user();

        $this->authService->changePassword($driver, $request->validated());

        return $this->success(null, 'Password changed successfully.');
    }
}
