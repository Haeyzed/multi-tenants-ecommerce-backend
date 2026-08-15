<?php

declare(strict_types=1);

namespace App\Enums\Tenant\Loyalty;

/**
 * Lifecycle state of a customer loyalty account.
 */
enum LoyaltyAccountStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
}
