<?php

declare(strict_types=1);

namespace App\Services\Tenant\Auth;

use App\Models\Tenant\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Permission\Exceptions\RoleDoesNotExist;
use Spatie\Permission\PermissionRegistrar;

/**
 * Tenant authentication, registration, profile, and password workflows.
 */
class AuthService
{
    /**
     * Register a new tenant user and issue a Sanctum API token.
     *
     * @param  array{first_name: string, last_name: string, email: string, phone?: string|null, password: string}  $data
     * @return array{user: User, token: string}
     */
    public function register(array $data): array
    {
        $user = User::query()->create($data);

        try {
            $user->assignRole('customer');
        } catch (RoleDoesNotExist) {
            // Role may not exist yet in a fresh tenant DB without seeding.
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $token = $user->createToken('api')->plainTextToken;

        return [
            'user' => $user->load(['roles', 'permissions']),
            'token' => $token,
        ];
    }

    /**
     * Authenticate a tenant user and issue a Sanctum API token.
     *
     * @param  array{email: string, password: string}  $credentials
     * @return array{user: User, token: string}
     *
     * @throws ValidationException
     */
    public function login(array $credentials): array
    {
        $user = User::query()->where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        $token = $user->createToken('api')->plainTextToken;

        return [
            'user' => $user->load(['roles', 'permissions']),
            'token' => $token,
        ];
    }

    /**
     * Revoke the current Sanctum access token for the given user.
     */
    public function logout(User $user): void
    {
        $token = $user->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }
    }

    /**
     * Send a password reset link without revealing whether the email exists.
     */
    public function forgotPassword(string $email): void
    {
        Password::broker('tenant_users')->sendResetLink([
            'email' => $email,
        ]);
    }

    /**
     * Reset a tenant user's password using the password broker.
     *
     * @param  array{email: string, password: string, password_confirmation: string, token: string}  $data
     *
     * @throws ValidationException
     */
    public function resetPassword(array $data): void
    {
        $status = Password::broker('tenant_users')->reset(
            $data,
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                ])->save();

                $user->tokens()->delete();
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }
    }

    /**
     * Update the authenticated tenant user's profile and optional avatar.
     *
     * @param  array{first_name?: string, last_name?: string, email?: string, phone?: string|null}  $data
     */
    public function updateProfile(User $user, array $data, ?UploadedFile $avatar = null): User
    {
        $user->fill($data);
        $user->save();

        if ($avatar !== null) {
            $user->clearMediaCollection('avatar');
            $user->addMedia($avatar)->toMediaCollection('avatar');
        }

        return $user->fresh(['roles', 'permissions']) ?? $user;
    }

    /**
     * Change the authenticated tenant user's password and revoke all tokens.
     *
     * @param  array{current_password: string, password: string}  $data
     *
     * @throws ValidationException
     */
    public function changePassword(User $user, array $data): void
    {
        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => [__('auth.password')],
            ]);
        }

        $user->forceFill([
            'password' => $data['password'],
        ])->save();

        $user->tokens()->delete();
    }
}
