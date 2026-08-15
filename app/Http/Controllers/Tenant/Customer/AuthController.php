<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Customer\Auth\ChangePasswordRequest;
use App\Http\Requests\Tenant\Customer\Auth\ForgotPasswordRequest;
use App\Http\Requests\Tenant\Customer\Auth\LoginRequest;
use App\Http\Requests\Tenant\Customer\Auth\RegisterRequest;
use App\Http\Requests\Tenant\Customer\Auth\ResetPasswordRequest;
use App\Http\Requests\Tenant\Customer\Auth\StoreAvatarRequest;
use App\Http\Requests\Tenant\Customer\Auth\UpdateProfileRequest;
use App\Http\Requests\Tenant\Customer\Auth\VerifyEmailRequest;
use App\Http\Requests\Tenant\Customer\StoreCustomerAddressRequest;
use App\Http\Requests\Tenant\Customer\UpdateCustomerAddressRequest;
use App\Http\Resources\Media\MediaResource;
use App\Http\Resources\Tenant\Customer\CustomerAddressResource;
use App\Http\Resources\Tenant\Customer\CustomerResource;
use App\Models\Tenant\Customer;
use App\Models\Tenant\CustomerAddress;
use App\Services\Tenant\Customer\CustomerAuthService;
use App\Services\Tenant\Customer\CustomerService;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Customer authentication, profile, and address endpoints.
 */
class AuthController extends Controller
{
    public function __construct(
        private readonly CustomerAuthService $authService,
        private readonly CustomerService $customerService,
    ) {}

    /**
     * Register a customer and return a Sanctum token.
     */
    #[Response(
        status: 201,
        description: 'Registered customer with API token.',
        type: 'array{success: true, message: string, data: array{customer: CustomerResource, token: string}, meta: null, errors: null}',
    )]
    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->authService->register($request->validated());

        return $this->created([
            'customer' => new CustomerResource($result['customer']),
            'token' => $result['token'],
        ], 'Registered successfully.');
    }

    /**
     * Authenticate a customer and return a Sanctum token.
     */
    #[Response(
        status: 200,
        description: 'Authenticated customer with API token.',
        type: 'array{success: true, message: string, data: array{customer: CustomerResource, token: string}, meta: null, errors: null}',
    )]
    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login($request->validated());

        return $this->success([
            'customer' => new CustomerResource($result['customer']),
            'token' => $result['token'],
        ], 'Logged in successfully.');
    }

    /**
     * Revoke the current customer access token.
     */
    #[Response(
        status: 200,
        description: 'Logout confirmation.',
        type: 'array{success: true, message: string, data: null, meta: null, errors: null}',
    )]
    public function logout(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = Auth::guard('customer')->user();

        $this->authService->logout($customer);

        return $this->success(null, 'Logged out successfully.');
    }

    /**
     * Request a password reset link for a customer.
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
     * Reset a customer's password using a reset token.
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
     * Return the authenticated customer.
     */
    #[Response(
        status: 200,
        description: 'Authenticated customer profile.',
        type: 'array{success: true, message: string, data: CustomerResource, meta: null, errors: null}',
    )]
    public function me(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = Auth::guard('customer')->user();

        return $this->success(
            new CustomerResource($customer),
            'Profile retrieved successfully.',
        );
    }

    /**
     * Update the authenticated customer's profile.
     */
    #[Response(
        status: 200,
        description: 'Updated customer profile.',
        type: 'array{success: true, message: string, data: CustomerResource, meta: null, errors: null}',
    )]
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = Auth::guard('customer')->user();

        $data = $request->safe()->except(['avatar']);

        $customer = $this->customerService->updateProfile(
            $customer,
            $data,
            $request->file('avatar'),
        );

        return $this->updated(
            new CustomerResource($customer),
            'Profile updated successfully.',
        );
    }

    /**
     * Upload or replace the authenticated customer's avatar.
     */
    #[Response(
        status: 200,
        description: 'Uploaded avatar media.',
        type: 'array{success: true, message: string, data: MediaResource, meta: null, errors: null}',
    )]
    public function storeAvatar(StoreAvatarRequest $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = Auth::guard('customer')->user();

        $media = $this->customerService->replaceAvatar($customer, $request->file('avatar'));

        return $this->updated(
            new MediaResource($media),
            'Avatar uploaded successfully.',
        );
    }

    /**
     * Delete the authenticated customer's avatar.
     */
    #[Response(
        status: 200,
        description: 'Avatar deleted.',
        type: 'array{success: true, message: string, data: null, meta: null, errors: null}',
    )]
    public function destroyAvatar(): JsonResponse
    {
        /** @var Customer $customer */
        $customer = Auth::guard('customer')->user();

        $this->customerService->removeAvatar($customer);

        return $this->deleted('Avatar deleted successfully.');
    }

    /**
     * Change the authenticated customer's password.
     */
    #[Response(
        status: 200,
        description: 'Password change confirmation.',
        type: 'array{success: true, message: string, data: null, meta: null, errors: null}',
    )]
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = Auth::guard('customer')->user();

        $this->authService->changePassword($customer, $request->validated());

        return $this->success(null, 'Password changed successfully.');
    }

    /**
     * Verify the authenticated customer's email address.
     */
    #[Response(
        status: 200,
        description: 'Email verified.',
        type: 'array{success: true, message: string, data: CustomerResource, meta: null, errors: null}',
    )]
    public function verifyEmail(VerifyEmailRequest $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = Auth::guard('customer')->user();

        $this->authService->verifyEmail($customer, $request->validated('token'));

        return $this->success(
            new CustomerResource($customer->fresh()),
            'Email verified successfully.',
        );
    }

    /**
     * Resend the email verification notification.
     */
    #[Response(
        status: 200,
        description: 'Verification email resent.',
        type: 'array{success: true, message: string, data: null, meta: null, errors: null}',
    )]
    public function resendVerification(): JsonResponse
    {
        /** @var Customer $customer */
        $customer = Auth::guard('customer')->user();

        $this->authService->resendVerification($customer);

        return $this->success(null, 'Verification email sent.');
    }

    /**
     * Deactivate the authenticated customer's account.
     */
    #[Response(
        status: 200,
        description: 'Account deactivated.',
        type: 'array{success: true, message: string, data: null, meta: null, errors: null}',
    )]
    public function destroyAccount(): JsonResponse
    {
        /** @var Customer $customer */
        $customer = Auth::guard('customer')->user();

        $this->customerService->deactivateAccount($customer);

        return $this->deleted('Account deactivated successfully.');
    }

    /**
     * List the authenticated customer's addresses.
     */
    #[Response(
        status: 200,
        description: 'Customer addresses.',
        type: 'array{success: true, message: string, data: CustomerAddressResource[], meta: null, errors: null}',
    )]
    public function indexAddresses(): JsonResponse
    {
        /** @var Customer $customer */
        $customer = Auth::guard('customer')->user();

        return $this->success(
            CustomerAddressResource::collection($this->customerService->listAddresses($customer)),
            'Addresses retrieved successfully.',
        );
    }

    /**
     * Store a new address for the authenticated customer.
     */
    #[Response(
        status: 201,
        description: 'Created address.',
        type: 'array{success: true, message: string, data: CustomerAddressResource, meta: null, errors: null}',
    )]
    public function storeAddress(StoreCustomerAddressRequest $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = Auth::guard('customer')->user();

        $address = $this->customerService->storeAddress($customer, $request->validated());

        return $this->created(
            new CustomerAddressResource($address),
            'Address created successfully.',
        );
    }

    /**
     * Update an address belonging to the authenticated customer.
     */
    #[Response(
        status: 200,
        description: 'Updated address.',
        type: 'array{success: true, message: string, data: CustomerAddressResource, meta: null, errors: null}',
    )]
    public function updateAddress(UpdateCustomerAddressRequest $request, CustomerAddress $address): JsonResponse
    {
        /** @var Customer $customer */
        $customer = Auth::guard('customer')->user();

        $address = $this->customerService->updateAddress($customer, $address, $request->validated());

        return $this->updated(
            new CustomerAddressResource($address),
            'Address updated successfully.',
        );
    }

    /**
     * Delete an address belonging to the authenticated customer.
     */
    #[Response(
        status: 200,
        description: 'Address deleted.',
        type: 'array{success: true, message: string, data: null, meta: null, errors: null}',
    )]
    public function destroyAddress(CustomerAddress $address): JsonResponse
    {
        /** @var Customer $customer */
        $customer = Auth::guard('customer')->user();

        $this->customerService->destroyAddress($customer, $address);

        return $this->deleted('Address deleted successfully.');
    }

    /**
     * Mark an address as the default for the authenticated customer.
     */
    #[Response(
        status: 200,
        description: 'Default address updated.',
        type: 'array{success: true, message: string, data: CustomerAddressResource, meta: null, errors: null}',
    )]
    public function makeDefaultAddress(CustomerAddress $address): JsonResponse
    {
        /** @var Customer $customer */
        $customer = Auth::guard('customer')->user();

        $address = $this->customerService->makeDefault($customer, $address);

        return $this->updated(
            new CustomerAddressResource($address),
            'Default address updated successfully.',
        );
    }
}
