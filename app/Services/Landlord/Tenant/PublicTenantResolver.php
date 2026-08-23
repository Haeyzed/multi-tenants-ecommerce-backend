<?php

declare(strict_types=1);

namespace App\Services\Landlord\Tenant;

use App\Enums\Landlord\TenantStatus;
use App\Models\Landlord\Domain;
use App\Models\Landlord\Tenant;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Resolve a tenant from a hostname for unauthenticated public bootstrap.
 */
class PublicTenantResolver
{
    /**
     * Resolve a tenant by hostname/domain for public auth branding.
     *
     * Does not require the storefront profile to be marked `is_public`
     * (directory listing). Returns safe public branding only.
     *
     * @throws NotFoundHttpException
     */
    public function resolveByDomain(string $domain): Tenant
    {
        $normalized = $this->normalizeDomain($domain);

        if ($normalized === '' || $this->isCentralDomain($normalized)) {
            throw new NotFoundHttpException('Tenant not found for this domain.');
        }

        $withoutPort = $this->stripPort($normalized);

        /** @var Domain|null $record */
        $record = Domain::query()
            ->where(function ($query) use ($normalized, $withoutPort): void {
                $query->where('domain', $normalized);

                if ($withoutPort !== $normalized) {
                    $query->orWhere('domain', $withoutPort);
                }
            })
            ->first();

        if ($record === null) {
            throw new NotFoundHttpException('Tenant not found for this domain.');
        }

        /** @var Tenant|null $tenant */
        $tenant = Tenant::query()
            ->with(['profile.country', 'profile.state', 'profile.city', 'profile.currency', 'profile.language', 'domains'])
            ->find($record->tenant_id);

        if ($tenant === null) {
            throw new NotFoundHttpException('Tenant not found for this domain.');
        }

        return $tenant;
    }

    /**
     * Normalize a host string for Domain table lookup.
     */
    public function normalizeDomain(string $domain): string
    {
        $value = Str::lower(trim($domain));

        if ($value === '') {
            return '';
        }

        if (str_contains($value, '://')) {
            $host = parse_url($value, PHP_URL_HOST);
            $port = parse_url($value, PHP_URL_PORT);

            if (is_string($host) && $host !== '') {
                return $port ? "{$host}:{$port}" : $host;
            }
        }

        return $this->stripTrailingDot($value);
    }

    /**
     * Whether the host is a configured central (landlord) domain.
     */
    public function isCentralDomain(string $domain): bool
    {
        $host = $this->stripPort($domain);

        foreach (config('tenancy.identification.central_domains', []) as $central) {
            if ($host === Str::lower((string) $central)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the tenant may present a normal login form.
     */
    public function allowsLogin(Tenant $tenant): bool
    {
        if (! $tenant->is_active) {
            return false;
        }

        return $tenant->status === TenantStatus::Active;
    }

    private function stripPort(string $domain): string
    {
        if (preg_match('/^\[(.+)\]:(\d+)$/', $domain, $matches) === 1) {
            return $matches[1];
        }

        if (preg_match('/^([^:]+):(\d+)$/', $domain, $matches) === 1) {
            return $matches[1];
        }

        return $domain;
    }

    private function stripTrailingDot(string $domain): string
    {
        return rtrim($domain, '.');
    }
}
