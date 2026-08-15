<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Media\MediaCollection;
use App\Enums\Media\MediaConversion;
use App\Enums\Tenant\Customer\CustomerStatus;
use App\Events\CustomerEmailVerificationRequested;
use Database\Factories\Tenant\CustomerFactory;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

#[Fillable(['first_name', 'last_name', 'email', 'phone', 'password', 'status'])]
#[Hidden(['password', 'remember_token'])]
class Customer extends Authenticatable implements HasMedia, MustVerifyEmail
{
    /** @use HasFactory<CustomerFactory> */
    use CanResetPassword, HasApiTokens, HasFactory, InteractsWithMedia, MustVerifyEmailTrait, Notifiable, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'status' => CustomerStatus::class,
        ];
    }

    /**
     * Customer shipping and billing addresses.
     *
     * @return HasMany<CustomerAddress, $this>
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class);
    }

    /**
     * Shopping carts for this customer.
     *
     * @return HasMany<Cart, $this>
     */
    public function carts(): HasMany
    {
        return $this->hasMany(Cart::class);
    }

    /**
     * Sales orders for this customer.
     *
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Register media collections for the customer.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(MediaCollection::Avatar->value)
            ->singleFile()
            ->acceptsMimeTypes(config('media.mimes.image', []));
    }

    /**
     * Register image conversions for avatar.
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        $thumb = config('media.conversions.thumb');

        $this->addMediaConversion(MediaConversion::Thumb->value)
            ->fit(Fit::Max, (int) $thumb['width'], (int) $thumb['height'])
            ->nonQueued()
            ->performOnCollections(MediaCollection::Avatar->value);
    }

    /**
     * Resolve the public avatar URL when present.
     */
    public function getAvatarUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl(MediaCollection::Avatar->value) ?: null;
    }

    /**
     * Full display name.
     */
    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    /**
     * Whether the customer is allowed to log in.
     */
    public function isLoginAllowed(): bool
    {
        return $this->status === CustomerStatus::Active;
    }

    /**
     * Send the email verification notification via the notification template system.
     */
    public function sendEmailVerificationNotification(): void
    {
        $token = Str::random(64);

        Cache::put(
            'customer.email_verify.'.$this->id,
            hash('sha256', $token),
            now()->addHours(24),
        );

        event(new CustomerEmailVerificationRequested($this, $token));
    }

    /**
     * @param  Builder<Customer>  $query
     * @param  array{
     *     search?: string|null,
     *     status?: string|null
     * }  $params
     * @return Builder<Customer>
     */
    public function scopeFilter(Builder $query, array $params = []): Builder
    {
        return $query
            ->when($params['search'] ?? null, function (Builder $query, string $search): void {
                $like = '%'.$search.'%';

                $query->where(function (Builder $query) use ($like): void {
                    $query->where('first_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('phone', 'like', $like);
                });
            })
            ->when($params['status'] ?? null, function (Builder $query, string $status): void {
                $query->where('status', $status);
            });
    }

    /**
     * Apply a whitelist of sorts.
     *
     * @param  Builder<Customer>  $query
     * @return Builder<Customer>
     */
    public function scopeApplySort(Builder $query, ?string $sort = null): Builder
    {
        $allowed = ['first_name', 'last_name', 'email', 'status', 'created_at', 'updated_at', 'last_login_at', 'id'];
        $sort = $sort ?: '-created_at';
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');

        if (! in_array($column, $allowed, true)) {
            $column = 'created_at';
            $direction = 'desc';
        }

        return $query->orderBy($column, $direction)->orderBy('id');
    }
}
