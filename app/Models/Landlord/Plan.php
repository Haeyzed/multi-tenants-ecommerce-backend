<?php

declare(strict_types=1);

namespace App\Models\Landlord;

use App\Enums\Landlord\BillingInterval;
use App\Models\Landlord\World\Currency;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Subscription plan offered on the platform.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $price
 * @property string $currency
 * @property BillingInterval $billing_interval
 * @property int $trial_days
 * @property bool $is_active
 * @property bool $is_public
 */
class Plan extends Model
{
    use CentralConnection, HasSlug;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'currency',
        'currency_id',
        'billing_interval',
        'billing_interval_count',
        'trial_days',
        'is_active',
        'is_public',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'billing_interval' => BillingInterval::class,
            'billing_interval_count' => 'integer',
            'trial_days' => 'integer',
            'is_active' => 'boolean',
            'is_public' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Configure slug generation from the plan name.
     */
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug')
            ->skipGenerateWhen(fn (): bool => filled($this->slug));
    }

    /**
     * Whether the plan requires payment gateway initialization.
     */
    public function isFree(): bool
    {
        return bccomp((string) $this->price, '0.00', 2) === 0;
    }

    /**
     * @return BelongsTo<Currency, $this>
     */
    public function currencyRelation(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }

    /**
     * Features attached to this plan.
     *
     * @return BelongsToMany<Feature, $this>
     */
    public function features(): BelongsToMany
    {
        return $this->belongsToMany(Feature::class, 'plan_features')
            ->withPivot(['is_enabled', 'limit'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * @param  Builder<$this>  $query
     * @param  array{search?: string|null, is_active?: bool|null, is_public?: bool|null}  $params
     * @return Builder<$this>
     */
    public function scopeFilter(Builder $query, array $params = []): Builder
    {
        return $query
            ->when($params['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', '%'.$search.'%')
                        ->orWhere('slug', 'like', '%'.$search.'%');
                });
            })
            ->when(array_key_exists('is_active', $params) && $params['is_active'] !== null, function (Builder $query) use ($params): void {
                $query->where('is_active', (bool) $params['is_active']);
            })
            ->when(array_key_exists('is_public', $params) && $params['is_public'] !== null, function (Builder $query) use ($params): void {
                $query->where('is_public', (bool) $params['is_public']);
            });
    }

    /**
     * Scope publicly listable plans.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopePubliclyAvailable(Builder $query): Builder
    {
        return $query->where('is_public', true)->where('is_active', true);
    }
}
