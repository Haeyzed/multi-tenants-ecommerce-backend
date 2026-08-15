<?php

declare(strict_types=1);

namespace App\Services\Landlord\Feature;

use App\Models\Landlord\Feature;
use App\Models\Landlord\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Centralized plan feature checks for the current (or given) tenant.
 *
 * Wraps FeatureAccessService so controllers/services share one entry point.
 */
class FeatureGate
{
    public function __construct(private readonly FeatureAccessService $features) {}

    /**
     * Whether the tenant's plan includes the feature.
     */
    public function allows(string $featureSlug, ?Tenant $tenant = null): bool
    {
        $tenant ??= $this->currentTenant();

        if ($tenant === null) {
            return false;
        }

        return $this->features->has($tenant, $featureSlug);
    }

    /**
     * Assert the feature is enabled or throw a validation exception.
     *
     * @throws ValidationException
     */
    public function assert(string $featureSlug, ?Tenant $tenant = null): void
    {
        if (! $this->allows($featureSlug, $tenant)) {
            throw ValidationException::withMessages([
                'feature' => "Your current plan does not include the [{$featureSlug}] feature.",
            ]);
        }
    }

    /**
     * Resolve the plan limit for a feature (null = unlimited or unavailable).
     */
    public function limit(string $featureSlug, ?Tenant $tenant = null): ?int
    {
        $tenant ??= $this->currentTenant();

        if ($tenant === null) {
            return null;
        }

        return $this->features->limit($tenant, $featureSlug);
    }

    /**
     * Features available on the tenant's current plan.
     *
     * @return Collection<int, Feature>
     */
    public function features(?Tenant $tenant = null): Collection
    {
        $tenant ??= $this->currentTenant();

        if ($tenant === null) {
            return collect();
        }

        return $this->features->featuresForTenant($tenant);
    }

    protected function currentTenant(): ?Tenant
    {
        $tenant = tenant();

        return $tenant instanceof Tenant ? $tenant : null;
    }
}
