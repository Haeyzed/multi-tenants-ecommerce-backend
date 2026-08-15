<?php

declare(strict_types=1);

namespace App\Services\Landlord\Tenant;

use App\Enums\Landlord\TenantStatus;
use App\Events\TenantProvisioned;
use App\Models\Landlord\Tenant;
use App\Models\Landlord\TenantProfile;
use App\Models\Tenant\User as TenantUser;
use App\Services\Landlord\Domain\DomainService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Landlord tenant lifecycle: CRUD and Stancl provisioning.
 */
class TenantService
{
    /**
     * Create a new tenant service.
     */
    public function __construct(private readonly DomainService $domainService) {}

    /**
     * Retrieve a paginated list of tenants.
     *
     * @param  array{search?: string|null, status?: string|null, is_active?: bool|null, per_page?: int|null}  $params
     * @return LengthAwarePaginator<int, Tenant>
     */
    public function list(array $params = []): LengthAwarePaginator
    {
        return Tenant::query()
            ->with(['domains', 'profile'])
            ->filter($params)
            ->latest()
            ->paginate($this->perPage($params));
    }

    /**
     * Retrieve tenant options as label/value pairs for select inputs.
     *
     * @param  array{search?: string|null, status?: string|null, is_active?: bool|null}  $params
     * @return Collection<int, array{label: string, value: string}>
     */
    public function options(array $params = []): Collection
    {
        return Tenant::query()
            ->filter($params)
            ->orderBy('name')
            ->get(['id', 'name', 'slug'])
            ->map(fn (Tenant $tenant): array => [
                'label' => $tenant->name,
                'value' => (string) $tenant->getTenantKey(),
            ])
            ->values();
    }

    /**
     * Create and provision a tenant (database, domain, profile, initial admin).
     *
     * @param  array{
     *     name: string,
     *     slug?: string|null,
     *     email?: string|null,
     *     phone?: string|null,
     *     status?: string|null,
     *     is_active?: bool|null,
     *     domain: string,
     *     admin: array{first_name: string, last_name: string, email: string, phone?: string|null, password: string},
     *     profile?: array{display_name?: string, description?: string|null, is_public?: bool}|null
     * }  $data
     *
     * @throws ValidationException
     * @throws Throwable
     */
    public function store(array $data): Tenant
    {
        $admin = $data['admin'];
        $domain = $data['domain'];
        $profileData = $data['profile'] ?? [];
        unset($data['admin'], $data['domain'], $data['profile']);

        $tenant = Tenant::query()->create([
            'name' => $data['name'],
            'slug' => $data['slug'] ?? null,
            'email' => $data['email'] ?? $admin['email'],
            'phone' => $data['phone'] ?? null,
            'status' => $data['status'] ?? TenantStatus::Active->value,
            'is_active' => $data['is_active'] ?? true,
        ]);

        $this->domainService->store($tenant, [
            'domain' => $domain,
            'is_primary' => true,
        ]);

        TenantProfile::query()->create([
            'tenant_id' => $tenant->getTenantKey(),
            'display_name' => $profileData['display_name'] ?? $tenant->name,
            'description' => $profileData['description'] ?? null,
            'email' => $tenant->email,
            'phone' => $tenant->phone,
            'is_public' => $profileData['is_public'] ?? false,
        ]);

        $tenant->run(function () use ($admin): void {
            $user = TenantUser::query()->create([
                'first_name' => $admin['first_name'],
                'last_name' => $admin['last_name'],
                'email' => $admin['email'],
                'phone' => $admin['phone'] ?? null,
                'password' => $admin['password'],
            ]);

            $user->assignRole('admin');
        });

        $tenant = $tenant->load(['domains', 'profile']);

        event(new TenantProvisioned($tenant));

        return $tenant;
    }

    /**
     * Retrieve a single tenant with relations.
     */
    public function show(Tenant $tenant): Tenant
    {
        return $tenant->load(['domains', 'profile', 'subscriptions.plan']);
    }

    /**
     * Update a tenant's platform fields.
     *
     * @param  array{name?: string, slug?: string, email?: string|null, phone?: string|null, status?: string, is_active?: bool}  $data
     */
    public function update(Tenant $tenant, array $data): Tenant
    {
        $tenant->update($data);

        return $tenant->fresh(['domains', 'profile']);
    }

    /**
     * Delete a tenant (Stancl pipeline deletes domains/database).
     */
    public function destroy(Tenant $tenant): void
    {
        DB::transaction(function () use ($tenant): void {
            $tenant->delete();
        });
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
