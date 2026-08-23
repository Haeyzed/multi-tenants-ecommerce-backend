<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Landlord\Tenant;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a tenant status becomes active.
 */
class TenantActivated
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Tenant $tenant) {}
}
