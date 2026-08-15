<?php

declare(strict_types=1);

namespace App\Services\Tenant\Customer;

use App\Enums\Tenant\Customer\CustomerStatus;
use App\Events\CustomerRegistered;
use App\Events\PasswordChanged;
use App\Events\PasswordResetRequested;
use App\Models\Tenant\Customer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Customer authentication and password workflows.
 */
class CustomerAuthService
{
    /**
     * Register a new customer and issue a Sanctum API token.
     *
     * @param  array{first_name: string, last_name: string, email: string, phone?: string|null, password: string}  $data
     * @return array{customer: Customer, token: string}
     */
    public function register(array $data): array
    {
        $customer = Customer::query()->create($data);

        event(new CustomerRegistered($customer));

        $token = $customer->createToken('api')->plainTextToken;

        $customer->sendEmailVerificationNotification();

        return [
            'customer' => $customer,
            'token' => $token,
        ];
    }

    /**
     * Authenticate a customer and issue a Sanctum API token.
     *
     * @param  array{email: string, password: string}  $credentials
     * @return array{customer: Customer, token: string}
     *
     * @throws ValidationException
     */
    public function login(array $credentials): array
    {
        $customer = Customer::query()->where('email', $credentials['email'])->first();

        if (! $customer || ! Hash::check($credentials['password'], $customer->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        if (! $customer->isLoginAllowed()) {
            throw ValidationException::withMessages([
                'email' => [$customer->status === CustomerStatus::Blocked
                    ? 'Your account has been blocked. Please contact support.'
                    : 'Your account is inactive. Please contact support.'],
            ]);
        }

        $customer->forceFill(['last_login_at' => now()])->save();

        $token = $customer->createToken('api')->plainTextToken;

        return [
            'customer' => $customer,
            'token' => $token,
        ];
    }

    /**
     * Revoke the current Sanctum access token for the given customer.
     */
    public function logout(Customer $customer): void
    {
        $token = $customer->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }
    }

    /**
     * Send a password reset link without revealing whether the email exists.
     */
    public function forgotPassword(string $email): void
    {
        $customer = Customer::query()->where('email', $email)->first();

        if ($customer === null) {
            return;
        }

        $token = Password::broker('customers')->createToken($customer);

        event(new PasswordResetRequested($customer, $token));
    }

    /**
     * Reset a customer's password using the password broker.
     *
     * @param  array{email: string, password: string, password_confirmation: string, token: string}  $data
     *
     * @throws ValidationException
     */
    public function resetPassword(array $data): void
    {
        $status = Password::broker('customers')->reset(
            $data,
            function (Customer $customer, string $password): void {
                $customer->forceFill([
                    'password' => $password,
                ])->save();

                $customer->tokens()->delete();

                event(new PasswordChanged($customer, 'reset'));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }
    }

    /**
     * Verify a customer's email using a cached token.
     *
     * @throws ValidationException
     */
    public function verifyEmail(Customer $customer, string $token): void
    {
        $cacheKey = 'customer.email_verify.'.$customer->id;
        $hashed = Cache::get($cacheKey);

        if ($hashed === null || ! hash_equals((string) $hashed, hash('sha256', $token))) {
            throw ValidationException::withMessages([
                'token' => ['Invalid or expired verification token.'],
            ]);
        }

        $customer->forceFill(['email_verified_at' => now()])->save();

        Cache::forget($cacheKey);
    }

    /**
     * Resend the email verification notification with throttling.
     *
     * @throws ValidationException
     */
    public function resendVerification(Customer $customer): void
    {
        if ($customer->hasVerifiedEmail()) {
            throw ValidationException::withMessages([
                'email' => ['Email address is already verified.'],
            ]);
        }

        $throttleKey = 'customer.email_verify.resend.'.$customer->id;

        if (Cache::has($throttleKey)) {
            throw ValidationException::withMessages([
                'email' => ['Please wait before requesting another verification email.'],
            ]);
        }

        Cache::put($throttleKey, true, now()->addMinute());

        $customer->sendEmailVerificationNotification();
    }

    /**
     * Change the authenticated customer's password and revoke all tokens.
     *
     * @param  array{current_password: string, password: string}  $data
     *
     * @throws ValidationException
     */
    public function changePassword(Customer $customer, array $data): void
    {
        if (! Hash::check($data['current_password'], $customer->password)) {
            throw ValidationException::withMessages([
                'current_password' => [__('auth.password')],
            ]);
        }

        $customer->forceFill([
            'password' => $data['password'],
        ])->save();

        $customer->tokens()->delete();

        event(new PasswordChanged($customer, 'changed'));
    }
}
