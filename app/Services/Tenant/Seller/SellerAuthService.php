<?php

declare(strict_types=1);

namespace App\Services\Tenant\Seller;

use App\Enums\Media\MediaCollection;
use App\Enums\Tenant\Marketplace\SellerStatus;
use App\Enums\Tenant\Marketplace\SellerVerificationStatus;
use App\Events\PasswordChanged;
use App\Events\PasswordResetRequested;
use App\Events\SellerRegistered;
use App\Models\Landlord\Tenant;
use App\Models\Tenant\Seller;
use App\Services\Landlord\Feature\UsageLimiter;
use App\Services\Media\MediaService;
use App\Services\Tenant\Commerce\CommerceSettingService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Seller authentication, registration, and password workflows.
 */
class SellerAuthService
{
    /**
     * Create a new class instance.
     *
     * @param  UsageLimiter  $usageLimiter
     * @param  CommerceSettingService  $commerceSettings
     * @param  MediaService  $mediaService
     */
    public function __construct(
        private readonly UsageLimiter $usageLimiter,
        private readonly CommerceSettingService $commerceSettings,
        private readonly MediaService $mediaService,
    ) {}

    /**
     * Register a marketplace seller (pending verification / inactive).
     *
     * @param  array{name: string, email: string, phone?: string|null, password: string, description?: string|null}  $data
     * @return array{seller: Seller, token: string}
     *
     * @throws ValidationException
     */
    public function register(array $data): array
    {
        if (! $this->commerceSettings->allowSellerRegistration()) {
            throw ValidationException::withMessages([
                'email' => ['Seller registration is disabled for this store.'],
            ]);
        }

        $tenant = tenant();
        if ($tenant instanceof Tenant && $tenant->activeSubscription() !== null) {
            $this->usageLimiter->assertCanCreate('sellers', $tenant);
        }

        $seller = Seller::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
            'description' => $data['description'] ?? null,
            'status' => SellerStatus::Inactive,
            'verification_status' => SellerVerificationStatus::Pending,
        ]);

        $token = $seller->createToken('api')->plainTextToken;

        event(new SellerRegistered($seller));

        return [
            'seller' => $seller,
            'token' => $token,
        ];
    }

    /**
     * Authenticate a seller and issue a Sanctum API token.
     *
     * @param  array{email: string, password: string}  $credentials
     * @return array{seller: Seller, token: string}
     *
     * @throws ValidationException
     */
    public function login(array $credentials): array
    {
        $seller = Seller::query()->where('email', $credentials['email'])->first();

        if (! $seller || $seller->password === null || ! Hash::check($credentials['password'], $seller->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        if (! $seller->isLoginAllowed()) {
            throw ValidationException::withMessages([
                'email' => [$seller->status === SellerStatus::Suspended
                    ? 'Your account has been suspended. Please contact support.'
                    : 'Your account has been rejected. Please contact support.'],
            ]);
        }

        $seller->forceFill(['last_login_at' => now()])->save();

        $token = $seller->createToken('api')->plainTextToken;

        return [
            'seller' => $seller,
            'token' => $token,
        ];
    }

    /**
     * Revoke the current Sanctum access token for the given seller.
     *
     * @param  Seller  $seller
     * @return void
     */
    public function logout(Seller $seller): void
    {
        $token = $seller->currentAccessToken();

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
        $seller = Seller::query()->where('email', $email)->first();

        if ($seller === null) {
            return;
        }

        $token = Password::broker('sellers')->createToken($seller);

        event(new PasswordResetRequested($seller, $token));
    }

    /**
     * Reset a seller's password using the password broker.
     *
     * @param  array{email: string, password: string, password_confirmation: string, token: string}  $data
     * @return void
     *
     * @throws ValidationException
     */
    public function resetPassword(array $data): void
    {
        $status = Password::broker('sellers')->reset(
            $data,
            function (Seller $seller, string $password): void {
                $seller->forceFill([
                    'password' => $password,
                ])->save();

                $seller->tokens()->delete();

                event(new PasswordChanged($seller, 'reset'));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }
    }

    /**
     * Change the authenticated seller's password and revoke all tokens.
     *
     * @param  Seller  $seller
     * @param  array{current_password: string, password: string}  $data
     * @return void
     *
     * @throws ValidationException
     */
    public function changePassword(Seller $seller, array $data): void
    {
        if ($seller->password === null || ! Hash::check($data['current_password'], $seller->password)) {
            throw ValidationException::withMessages([
                'current_password' => [__('auth.password')],
            ]);
        }

        $seller->forceFill([
            'password' => $data['password'],
        ])->save();

        $seller->tokens()->delete();

        event(new PasswordChanged($seller, 'changed'));
    }

    /**
     * Update the authenticated seller's profile (non-commission fields).
     *
     * @param  Seller  $seller
     * @param  array{name?: string, description?: string|null, email?: string, phone?: string|null}  $data
     * @return Seller
     */
    public function updateProfile(Seller $seller, array $data): Seller
    {
        $seller->fill($data);
        $seller->save();

        return $seller->fresh() ?? $seller;
    }

    /**
     * Replace the seller logo.
     *
     * @param  Seller  $seller
     * @param  UploadedFile  $logo
     * @return Media
     */
    public function replaceLogo(Seller $seller, UploadedFile $logo): Media
    {
        return $this->mediaService->replace($seller, $logo, MediaCollection::Logo);
    }

    /**
     * Remove the seller logo.
     *
     * @param  Seller  $seller
     * @return void
     */
    public function removeLogo(Seller $seller): void
    {
        $this->mediaService->removeCollection($seller, MediaCollection::Logo);
    }
}
