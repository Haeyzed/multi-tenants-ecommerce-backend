<?php

declare(strict_types=1);

namespace App\Services\Landlord\Feature;

use App\Models\Landlord\Feature;
use App\Models\Landlord\Tenant;
use Illuminate\Support\Collection;

/**
 * Resolves feature access for a tenant from its active/trialing subscription plan.
 */
class FeatureAccessService
{
    /**
     * Whether the tenant's current plan includes an enabled feature.
     *
     * @param  Tenant  $tenant
     * @param  string  $featureSlug
     * @return bool
     */
    public function has(Tenant $tenant, string $featureSlug): bool
    {
        $pivot = $this->featurePivot($tenant, $featureSlug);

        return $pivot !== null && (bool) ($pivot['is_enabled'] ?? false);
    }

    /**
     * Resolve the numeric limit for a feature, or null when unlimited / not present.
     *
     * @param  Tenant  $tenant
     * @param  string  $featureSlug
     * @return ?int
     */
    public function limit(Tenant $tenant, string $featureSlug): ?int
    {
        $pivot = $this->featurePivot($tenant, $featureSlug);

        if ($pivot === null || ! (bool) ($pivot['is_enabled'] ?? false)) {
            return null;
        }

        $limit = $pivot['limit'] ?? null;

        return $limit === null ? null : (int) $limit;
    }

    /**
     * Whether the tenant can use a feature given the current usage count.
     *
     * @param  Tenant  $tenant
     * @param  string  $featureSlug
     * @param  int  $currentUsage
     * @return bool
     */
    public function canUse(Tenant $tenant, string $featureSlug, int $currentUsage = 0): bool
    {
        if (! $this->has($tenant, $featureSlug)) {
            return false;
        }

        $limit = $this->limit($tenant, $featureSlug);

        if ($limit === null) {
            return true;
        }

        return $currentUsage < $limit;
    }

    /**
     * List features available on the tenant's current plan with pivot data.
     *
     * @param  Tenant  $tenant
     * @return Collection<int, Feature>
     */
    public function featuresForTenant(Tenant $tenant): Collection
    {
        $subscription = $tenant->activeSubscription();

        if ($subscription === null) {
            return collect();
        }

        $subscription->loadMissing('plan.features');

        return $subscription->plan?->features ?? collect();
    }

    /**
     * Feature pivot.
     *
     * @param  Tenant  $tenant
     * @param  string  $featureSlug
     * @return array{is_enabled?: bool|int|string, limit?: int|string|null}|null
     */
    protected function featurePivot(Tenant $tenant, string $featureSlug): ?array
    {
        $feature = $this->featuresForTenant($tenant)
            ->first(fn (Feature $feature): bool => $feature->slug === $featureSlug);

        if ($feature === null) {
            return null;
        }

        /** @var array{is_enabled?: bool|int|string, limit?: int|string|null} $pivot */
        $pivot = $feature->pivot?->toArray() ?? [];

        return $pivot;
    }
}
