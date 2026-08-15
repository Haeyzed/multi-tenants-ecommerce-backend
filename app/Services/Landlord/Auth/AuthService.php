<?php

declare(strict_types=1);

namespace App\Services\Landlord\Auth;

use App\Enums\Media\MediaCollection;
use App\Events\PasswordChanged;
use App\Events\PasswordResetRequested;
use App\Models\Landlord\User;
use App\Services\Media\MediaService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Landlord authentication, profile, and password workflows.
 */
class AuthService
{
    public function __construct(private readonly MediaService $mediaService) {}

    /**
     * Authenticate a landlord user and issue a Sanctum API token.
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
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            return;
        }

        $token = Password::broker('landlord_users')->createToken($user);

        event(new PasswordResetRequested($user, $token));
    }

    /**
     * Reset a landlord user's password using the password broker.
     *
     * @param  array{email: string, password: string, password_confirmation: string, token: string}  $data
     *
     * @throws ValidationException
     */
    public function resetPassword(array $data): void
    {
        $status = Password::broker('landlord_users')->reset(
            $data,
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                ])->save();

                $user->tokens()->delete();

                event(new PasswordChanged($user, 'reset'));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }
    }

    /**
     * Update the authenticated landlord user's profile and optional avatar.
     *
     * @param  array{first_name?: string, last_name?: string, email?: string, phone?: string|null}  $data
     */
    public function updateProfile(User $user, array $data, ?UploadedFile $avatar = null): User
    {
        $user->fill($data);
        $user->save();

        if ($avatar !== null) {
            $this->mediaService->replace($user, $avatar, MediaCollection::Avatar);
        }

        return $user->fresh(['roles', 'permissions']) ?? $user;
    }

    /**
     * Replace the authenticated user's avatar.
     */
    public function replaceAvatar(User $user, UploadedFile $avatar): Media
    {
        return $this->mediaService->replace($user, $avatar, MediaCollection::Avatar);
    }

    /**
     * Remove the authenticated user's avatar.
     */
    public function removeAvatar(User $user): void
    {
        $this->mediaService->removeCollection($user, MediaCollection::Avatar);
    }

    /**
     * Change the authenticated landlord user's password and revoke all tokens.
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

        event(new PasswordChanged($user, 'changed'));
    }
}
