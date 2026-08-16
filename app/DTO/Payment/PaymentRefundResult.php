<?php

declare(strict_types=1);

namespace App\DTO\Payment;

/**
 * Result of a gateway refund request.
 */
readonly class PaymentRefundResult
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public bool $successful,
        public ?string $providerRefundId = null,
        public ?string $amount = null,
        public ?string $currency = null,
        public array $raw = [],
        public ?string $message = null,
        public bool $ambiguous = false,
    ) {}
}
