<?php

declare(strict_types=1);

namespace App\Services\Tenant\Driver;

use App\Enums\Tenant\Driver\DriverStatus;
use App\Events\PasswordChanged;
use App\Events\PasswordResetRequested;
use App\Models\Tenant\Driver;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Driver authentication and password workflows.
 */
class DriverAuthService
{
    /**
     * Authenticate a driver and issue a Sanctum API token.
     *
     * @param  array{email: string, password: string}  $credentials
     * @return array{driver: Driver, token: string}
     *
     * @throws ValidationException
     */
    public function login(array $credentials): array
    {
        $driver = Driver::query()->where('email', $credentials['email'])->first();

        if (! $driver || ! Hash::check($credentials['password'], $driver->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        if (! $driver->isLoginAllowed()) {
            throw ValidationException::withMessages([
                'email' => [$driver->status === DriverStatus::Blocked
                    ? 'Your account has been blocked. Please contact support.'
                    : 'Your account is inactive. Please contact support.'],
            ]);
        }

        $driver->forceFill(['last_login_at' => now()])->save();

        $token = $driver->createToken('api')->plainTextToken;

        return [
            'driver' => $driver,
            'token' => $token,
        ];
    }

    /**
     * Revoke the current Sanctum access token for the given driver.
     *
     * @param  Driver  $driver
     * @return void
     */
    public function logout(Driver $driver): void
    {
        $token = $driver->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }
    }

    /**
     * Send a password reset link without revealing whether the email exists.
     *
     * @param  string  $email
     * @return void
     */
    public function forgotPassword(string $email): void
    {
        $driver = Driver::query()->where('email', $email)->first();

        if ($driver === null) {
            return;
        }

        $token = Password::broker('drivers')->createToken($driver);

        event(new PasswordResetRequested($driver, $token));
    }

    /**
     * Reset a driver's password using the password broker.
     *
     * @param  array{email: string, password: string, password_confirmation: string, token: string}  $data
     * @return void
     *
     * @throws ValidationException
     */
    public function resetPassword(array $data): void
    {
        $status = Password::broker('drivers')->reset(
            $data,
            function (Driver $driver, string $password): void {
                $driver->forceFill([
                    'password' => $password,
                ])->save();

                $driver->tokens()->delete();

                event(new PasswordChanged($driver, 'reset'));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }
    }

    /**
     * Change the authenticated driver's password and revoke all tokens.
     *
     * @param  Driver  $driver
     * @param  array{current_password: string, password: string}  $data
     * @return void
     *
     * @throws ValidationException
     */
    public function changePassword(Driver $driver, array $data): void
    {
        if (! Hash::check($data['current_password'], $driver->password)) {
            throw ValidationException::withMessages([
                'current_password' => [__('auth.password')],
            ]);
        }

        $driver->forceFill([
            'password' => $data['password'],
        ])->save();

        $driver->tokens()->delete();

        event(new PasswordChanged($driver, 'changed'));
    }
}
