<?php

declare(strict_types=1);

namespace App\Enums\Tenant\Customer;

/**
 * Built-in customer segmentation rule evaluators.
 */
enum CustomerSegmentRule: string
{
    case NewCustomer = 'new_customer';
    case ReturningCustomer = 'returning_customer';
    case HighValue = 'high_value';
    case Inactive = 'inactive';
    case FrequentBuyer = 'frequent_buyer';
    case WishlistCustomer = 'wishlist_customer';
    case AbandonedCartCustomer = 'abandoned_cart_customer';

    /**
     * Fallback threshold applied when a rule condition omits its value.
     */
    public function defaultValue(): int|string|null
    {
        return match ($this) {
            self::HighValue => '1000.00',
            self::Inactive => 90,
            self::FrequentBuyer => 5,
            default => null,
        };
    }
}
