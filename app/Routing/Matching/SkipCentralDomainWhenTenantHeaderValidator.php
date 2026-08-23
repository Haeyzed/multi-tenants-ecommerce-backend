<?php

declare(strict_types=1);

namespace App\Routing\Matching;

use App\Http\Middleware\InitializeTenancyByDomainOrHeader;
use Illuminate\Http\Request;
use Illuminate\Routing\Matching\ValidatorInterface;
use Illuminate\Routing\Route;

/**
 * When the BFF sends X-Tenant-Domain on the central Host, skip central-domain-bound
 * landlord routes so overlapping tenant URIs (e.g. /api/auth/login) can match.
 */
class SkipCentralDomainWhenTenantHeaderValidator implements ValidatorInterface
{
    public function matches(Route $route, Request $request): bool
    {
        $header = trim((string) $request->header(InitializeTenancyByDomainOrHeader::HEADER, ''));

        if ($header === '') {
            return true;
        }

        $routeDomain = $route->getDomain();

        if ($routeDomain === null || $routeDomain === '') {
            return true;
        }

        $centralDomains = config('tenancy.identification.central_domains', []);

        foreach ($centralDomains as $centralDomain) {
            if (! is_string($centralDomain) || $centralDomain === '') {
                continue;
            }

            if ($routeDomain === $centralDomain) {
                return false;
            }
        }

        return true;
    }
}
