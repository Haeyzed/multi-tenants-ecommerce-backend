<?php

declare(strict_types=1);

namespace App\Services\Tenant\Loyalty;

/**
 * Outcome of converting loyalty points into an order discount.
 */
final readonly class LoyaltyRedemptionResult
{
    /**
     * Create a new class instance.
     *
     * @param  int  $points
     * @param  string  $moneyValue
     */
    public function __construct(
        public int $points,
        public string $moneyValue,
    ) {}

    /**
     * An empty result used when nothing can be redeemed.
     *
     * @return self
     */
    public static function none(): self
    {
        return new self(0, '0.00');
    }

    /**
     * Is redeemable.
     *
     * @return bool
     */
    public function isRedeemable(): bool
    {
        return $this->points > 0 && bccomp($this->moneyValue, '0', 2) > 0;
    }
}
