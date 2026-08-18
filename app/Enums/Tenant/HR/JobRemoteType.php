<?php

declare(strict_types=1);

namespace App\Enums\Tenant\HR;

/**
 * Where a job opening is performed.
 */
enum JobRemoteType: string
{
    case OnSite = 'on_site';
    case Hybrid = 'hybrid';
    case Remote = 'remote';
}
