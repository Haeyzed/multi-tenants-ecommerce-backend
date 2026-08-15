<?php

declare(strict_types=1);

namespace App\Models\Landlord;

use App\Models\Landlord\World\City;
use App\Models\Landlord\World\Country;
use App\Models\Landlord\World\Currency;
use App\Models\Landlord\World\Language;
use App\Models\Landlord\World\State;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Public-facing store profile for a tenant.
 *
 * @property int $id
 * @property string $tenant_id
 * @property string $display_name
 * @property string $slug
 * @property bool $is_public
 */
class TenantProfile extends Model implements HasMedia
{
    use CentralConnection, HasSlug, InteractsWithMedia;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'display_name',
        'slug',
        'description',
        'email',
        'phone',
        'website',
        'address',
        'country_id',
        'state_id',
        'city_id',
        'currency_id',
        'language_id',
        'timezone',
        'is_public',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
        ];
    }

    /**
     * Configure slug generation from the display name.
     */
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('display_name')
            ->saveSlugsTo('slug')
            ->skipGenerateWhen(fn (): bool => filled($this->slug));
    }

    /**
     * Register media collections for logo and banner.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('logo')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif']);

        $this->addMediaCollection('banner')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif']);
    }

    /**
     * Public logo URL when present.
     */
    public function getLogoUrlAttribute(): ?string
    {
        /** @var Media|null $media */
        $media = $this->getFirstMedia('logo');

        return $media?->getUrl();
    }

    /**
     * Public banner URL when present.
     */
    public function getBannerUrlAttribute(): ?string
    {
        /** @var Media|null $media */
        $media = $this->getFirstMedia('banner');

        return $media?->getUrl();
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * @return BelongsTo<Country, $this>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    /**
     * @return BelongsTo<State, $this>
     */
    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class, 'state_id');
    }

    /**
     * @return BelongsTo<City, $this>
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class, 'city_id');
    }

    /**
     * @return BelongsTo<Currency, $this>
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }

    /**
     * @return BelongsTo<Language, $this>
     */
    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class, 'language_id');
    }
}
