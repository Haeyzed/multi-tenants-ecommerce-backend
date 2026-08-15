<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Media\MediaCollection;
use App\Enums\Media\MediaConversion;
use App\Enums\Tenant\Marketplace\CommissionType;
use App\Enums\Tenant\Marketplace\SellerStatus;
use App\Enums\Tenant\Marketplace\SellerVerificationStatus;
use Database\Factories\Tenant\SellerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
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
 * @property string|null $email
 * @property string|null $phone
 * @property SellerStatus $status
 * @property SellerVerificationStatus $verification_status
 * @property CommissionType|null $commission_type
 * @property string|null $commission_rate
 * @property string|null $commission_fixed_amount
 */
class Seller extends Model implements HasMedia
{
    /** @use HasFactory<SellerFactory> */
    use HasFactory, HasSlug, InteractsWithMedia, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'email',
        'phone',
        'status',
        'verification_status',
        'commission_type',
        'commission_rate',
        'commission_fixed_amount',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'inactive',
        'verification_status' => 'pending',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
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
     * @return HasMany<SellerOffer, $this>
     */
    public function offers(): HasMany
    {
        return $this->hasMany(SellerOffer::class);
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
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
