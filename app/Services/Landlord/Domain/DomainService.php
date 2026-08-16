<?php

declare(strict_types=1);

namespace App\Services\Landlord\Domain;

use App\Models\Landlord\Domain;
use App\Models\Landlord\Tenant;
use App\Services\Landlord\Feature\FeatureGate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Domain management for a tenant (Stancl Domain model).
 */
class DomainService
{
    public function __construct(private readonly FeatureGate $featureGate) {}

    /**
     * List domains for a tenant.
     *
     * @param  array{per_page?: int|null}  $params
     * @return LengthAwarePaginator<int, Domain>
     */
    public function list(Tenant $tenant, array $params = []): LengthAwarePaginator
    {
        return $tenant->domains()
            ->latest('id')
            ->paginate($this->perPage($params));
    }

    /**
     * Create a domain for the tenant.
     *
     * The first (primary) domain is always allowed for provisioning. Additional
     * domains require the custom-domain plan feature when a subscription exists.
     *
     * @param  array{domain: string, is_primary?: bool}  $data
     *
     * @throws ValidationException
     */
    public function store(Tenant $tenant, array $data): Domain
    {
        if ($tenant->activeSubscription() !== null && $tenant->domains()->exists()) {
            $this->featureGate->assert('custom-domain', $tenant);
        }

        return DB::transaction(function () use ($tenant, $data): Domain {
            $isPrimary = (bool) ($data['is_primary'] ?? false);

            if ($isPrimary || $tenant->domains()->count() === 0) {
                $this->clearPrimary($tenant);
                $isPrimary = true;
            }

            /** @var Domain $domain */
            $domain = $tenant->createDomain([
                'domain' => strtolower($data['domain']),
                'is_primary' => $isPrimary,
            ]);

            return $domain;
        });
    }

    /**
     * Show a domain belonging to the tenant.
     *
     * @throws NotFoundHttpException
     */
    public function show(Tenant $tenant, Domain $domain): Domain
    {
        $this->assertOwns($tenant, $domain);

        return $domain;
    }

    /**
     * Update a domain belonging to the tenant.
     *
     * @param  array{domain?: string, is_primary?: bool}  $data
     *
     * @throws NotFoundHttpException
     */
    public function update(Tenant $tenant, Domain $domain, array $data): Domain
    {
        $this->assertOwns($tenant, $domain);

        return DB::transaction(function () use ($tenant, $domain, $data): Domain {
            if (array_key_exists('domain', $data) && $data['domain'] !== null) {
                $domain->domain = strtolower((string) $data['domain']);
            }

            if (array_key_exists('is_primary', $data) && $data['is_primary']) {
                $this->clearPrimary($tenant);
                $domain->is_primary = true;
            } elseif (array_key_exists('is_primary', $data) && $data['is_primary'] === false && $domain->is_primary) {
                throw ValidationException::withMessages([
                    'is_primary' => ['Cannot unset the primary domain without selecting another.'],
                ]);
            }

            $domain->save();

            return $domain->fresh();
        });
    }

    /**
     * Delete a domain belonging to the tenant.
     *
     * @throws NotFoundHttpException
     * @throws ValidationException
     */
    public function destroy(Tenant $tenant, Domain $domain): void
    {
        $this->assertOwns($tenant, $domain);

        if ($tenant->domains()->count() <= 1) {
            throw ValidationException::withMessages([
                'domain' => ['A tenant must retain at least one domain.'],
            ]);
        }

        DB::transaction(function () use ($tenant, $domain): void {
            $wasPrimary = $domain->is_primary;
            $domain->delete();

            if ($wasPrimary) {
                /** @var Domain|null $next */
                $next = $tenant->domains()->oldest('id')->first();
                if ($next !== null) {
                    $next->update(['is_primary' => true]);
                }
            }
        });
    }

    /**
     * Mark a domain as primary for the tenant.
     *
     * @throws NotFoundHttpException
     */
    public function makePrimary(Tenant $tenant, Domain $domain): Domain
    {
        $this->assertOwns($tenant, $domain);

        return DB::transaction(function () use ($tenant, $domain): Domain {
            $this->clearPrimary($tenant);
            $domain->update(['is_primary' => true]);

            return $domain->fresh();
        });
    }

    /**
     * @throws NotFoundHttpException
     */
    private function assertOwns(Tenant $tenant, Domain $domain): void
    {
        if ((string) $domain->tenant_id !== (string) $tenant->getTenantKey()) {
            throw new NotFoundHttpException('Domain not found for this tenant.');
        }
    }

    private function clearPrimary(Tenant $tenant): void
    {
        $tenant->domains()->where('is_primary', true)->update(['is_primary' => false]);
    }

    /**
     * @param  array{per_page?: int|null}  $params
     */
    private function perPage(array $params): int
    {
        $perPage = (int) ($params['per_page'] ?? 15);

        return max(1, min($perPage, 100));
    }
}
