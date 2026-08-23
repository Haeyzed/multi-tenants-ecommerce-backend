<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Resolvers\DomainTenantResolver;
use Stancl\Tenancy\Tenancy;

/**
 * Initialize tenancy from the request Host, or from X-Tenant-Domain on central hosts.
 *
 * Supports the Next.js BFF which forwards tenant traffic to the central API origin
 * with an X-Tenant-Domain header when per-tenant DNS is unavailable.
 */
class InitializeTenancyByDomainOrHeader extends InitializeTenancyByDomain
{
    public const HEADER = 'X-Tenant-Domain';

    public function __construct(Tenancy $tenancy, DomainTenantResolver $resolver)
    {
        parent::__construct($tenancy, $resolver);
    }

    public function getDomain(Request $request): string
    {
        $host = $request->getHost();
        $central = config('tenancy.identification.central_domains', []);

        if (! in_array($host, $central, true)) {
            return $host;
        }

        $header = trim((string) $request->header(self::HEADER, ''));

        return $header !== '' ? $header : $host;
    }

    public function requestHasTenant(Request $request): bool
    {
        $domain = $this->getDomain($request);

        if ($domain === '') {
            return false;
        }

        return ! in_array($domain, config('tenancy.identification.central_domains'), true);
    }
}
