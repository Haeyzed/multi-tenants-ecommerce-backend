<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Auth\ChangePasswordRequest;
use App\Http\Requests\Tenant\Auth\ForgotPasswordRequest;
use App\Http\Requests\Tenant\Auth\LoginRequest;
use App\Http\Requests\Tenant\Auth\RegisterRequest;
use App\Http\Requests\Tenant\Auth\ResetPasswordRequest;
use App\Http\Requests\Tenant\Auth\UpdateProfileRequest;
use App\Http\Resources\Tenant\User\UserResource;
use App\Models\Tenant\User;
use App\Services\Tenant\Auth\AuthService;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Tenant authentication, registration, and profile endpoints.
 */
class AuthController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(private readonly AuthService $authService) {}

    /**
     * Register a tenant user and return a Sanctum token.
     */
    #[Response(
        status: 201,
        description: 'Registered tenant user with API token.',
        type: 'array{success: true, message: string, data: array{user: UserResource, token: string}, meta: null, errors: null}',
    )]
    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->authService->register($request->validated());

        return $this->created([
            'user' => new UserResource($result['user']),
            'token' => $result['token'],
        ], 'Registered successfully.');
    }

    /**
     * Authenticate a tenant user and return a Sanctum token.
     */
    #[Response(
        status: 200,
        description: 'Authenticated tenant user with API token.',
        type: 'array{success: true, message: string, data: array{user: UserResource, token: string}, meta: null, errors: null}',
    )]
    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login($request->validated());

        return $this->success([
            'user' => new UserResource($result['user']),
            'token' => $result['token'],
        ], 'Logged in successfully.');
    }

    /**
     * Revoke the current tenant access token.
     */
    #[Response(
        status: 200,
        description: 'Logout confirmation.',
        type: 'array{success: true, message: string, data: null, meta: null, errors: null}',
    )]
    public function logout(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::guard('tenant')->user();

        $this->authService->logout($user);

        return $this->success(null, 'Logged out successfully.');
    }

    /**
     * Request a password reset link for a tenant user.
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
     * Reset a tenant user's password using a reset token.
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
     * Return the authenticated tenant user.
     */
    #[Response(
        status: 200,
        description: 'Authenticated tenant user profile.',
        type: 'array{success: true, message: string, data: UserResource, meta: null, errors: null}',
    )]
    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::guard('tenant')->user();

        return $this->success(
            new UserResource($user->load(['roles', 'permissions'])),
            'Profile retrieved successfully.',
        );
    }

    /**
     * Update the authenticated tenant user's profile.
     */
    #[Response(
        status: 200,
        description: 'Updated tenant user profile.',
        type: 'array{success: true, message: string, data: UserResource, meta: null, errors: null}',
    )]
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::guard('tenant')->user();

        $data = $request->safe()->except(['avatar']);

        $user = $this->authService->updateProfile(
            $user,
            $data,
            $request->file('avatar'),
        );

        return $this->updated(
            new UserResource($user),
            'Profile updated successfully.',
        );
    }

    /**
     * Change the authenticated tenant user's password.
     */
    #[Response(
        status: 200,
        description: 'Password change confirmation.',
        type: 'array{success: true, message: string, data: null, meta: null, errors: null}',
    )]
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::guard('tenant')->user();

        $this->authService->changePassword($user, $request->validated());

        return $this->success(null, 'Password changed successfully.');
    }
}
