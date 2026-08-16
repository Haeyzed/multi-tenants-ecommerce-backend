<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Media\MediaCollection;
use App\Enums\Media\MediaConversion;
use App\Enums\Tenant\Marketplace\CommissionType;
use App\Enums\Tenant\Marketplace\SellerStatus;
use App\Enums\Tenant\Marketplace\SellerVerificationStatus;
use Database\Factories\Tenant\SellerFactory;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * Marketplace seller (vendor) belonging to the current tenant.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string $email
 * @property string|null $phone
 * @property string|null $password
 * @property SellerStatus $status
 * @property SellerVerificationStatus $verification_status
 * @property CommissionType|null $commission_type
 * @property string|null $commission_rate
 * @property string|null $commission_fixed_amount
 */
class Seller extends Authenticatable implements HasMedia
{
    /** @use HasFactory<SellerFactory> */
    use CanResetPassword, HasApiTokens, HasFactory, HasSlug, InteractsWithMedia, Notifiable, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'email',
        'phone',
        'password',
        'status',
        'verification_status',
        'commission_type',
        'commission_rate',
        'commission_fixed_amount',
        'seller_group_id',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'inactive',
        'verification_status' => 'pending',
    ];

    /**
     * Free unique email values on soft delete so they can be re-used.
     */
    protected static function booted(): void
    {
        static::deleting(function (Seller $seller): void {
            if ($seller->isForceDeleting()) {
                return;
            }

            $seller->email = 'deleted+'.$seller->id.'.'.time().'@deleted.local';

            if ($seller->phone !== null && $seller->phone !== '') {
                $seller->phone = 'deleted-'.$seller->id.'-'.time();
            }

            $seller->saveQuietly();
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'status' => SellerStatus::class,
            'verification_status' => SellerVerificationStatus::class,
            'commission_type' => CommissionType::class,
            'commission_rate' => 'decimal:4',
            'commission_fixed_amount' => 'decimal:2',
        ];
    }

    /**
     * Configure slug generation from the seller name.
     */
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug')
            ->skipGenerateWhen(fn (): bool => filled($this->slug));
    }

    /**
     * Whether the seller may list and sell offers.
     */
    public function canSell(): bool
    {
        return $this->verification_status === SellerVerificationStatus::Approved
            && $this->status === SellerStatus::Active;
    }

    /**
     * Whether the seller is allowed to log in.
     */
    public function isLoginAllowed(): bool
    {
        return $this->status !== SellerStatus::Suspended
            && $this->verification_status !== SellerVerificationStatus::Rejected;
    }

    /**
     * @return HasMany<SellerOffer, $this>
     */
    public function offers(): HasMany
    {
        return $this->hasMany(SellerOffer::class);
    }

    /**
     * Optional seller group classification.
     *
     * @return BelongsTo<SellerGroup, $this>
     */
    public function sellerGroup(): BelongsTo
    {
        return $this->belongsTo(SellerGroup::class);
    }

    /**
     * Register the single-file seller logo collection.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(MediaCollection::Logo->value)
            ->singleFile()
            ->acceptsMimeTypes(config('media.mimes.image', []));
    }

    /**
     * Register image conversions for seller logos.
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        $thumb = config('media.conversions.thumb');

        $this->addMediaConversion(MediaConversion::Thumb->value)
            ->fit(Fit::Max, (int) $thumb['width'], (int) $thumb['height'])
            ->nonQueued()
            ->performOnCollections(MediaCollection::Logo->value);
    }

    /**
     * Resolve the public logo URL when present.
     */
    public function getLogoUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl(MediaCollection::Logo->value) ?: null;
    }
}
