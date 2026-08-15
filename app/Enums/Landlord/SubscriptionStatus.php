<?php

declare(strict_types=1);

namespace App\Enums\Landlord;

/**
 * Provider-agnostic subscription lifecycle status.
 */
enum SubscriptionStatus: string
{
    case Trialing = 'trialing';
    case Active = 'active';
    case PastDue = 'past_due';
    case Paused = 'paused';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case Pending = 'pending';

    /**
     * Whether the subscription grants feature access.
     */
    public function grantsAccess(): bool
    {
        return match ($this) {
            self::Trialing, self::Active => true,
            default => false,
        };
    }
}
