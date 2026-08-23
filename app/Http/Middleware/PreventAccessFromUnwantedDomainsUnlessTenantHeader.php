<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Stancl\Tenancy\Middleware\PreventAccessFromUnwantedDomains;

/**
 * Same as Stancl PreventAccessFromUnwantedDomains, but allow tenant routes on a
 * central Host when X-Tenant-Domain identifies a tenant (BFF proxy pattern).
 */
class PreventAccessFromUnwantedDomainsUnlessTenantHeader extends PreventAccessFromUnwantedDomains
{
    protected function accessingTenantRouteFromCentralDomain(Request $request, Route $route): bool
    {
        if (! parent::accessingTenantRouteFromCentralDomain($request, $route)) {
            return false;
        }

        $header = trim((string) $request->header(InitializeTenancyByDomainOrHeader::HEADER, ''));

        return $header === '';
    }
}
