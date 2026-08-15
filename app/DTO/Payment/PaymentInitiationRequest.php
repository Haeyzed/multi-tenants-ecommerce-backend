<?php

declare(strict_types=1);

namespace App\DTO\Payment;

/**
 * Provider-agnostic payment initiation payload.
 */
readonly class PaymentInitiationRequest
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $amount,
        public string $currency,
        public string $email,
        public string $reference,
        public ?string $callbackUrl = null,
        public array $metadata = [],
        public ?string $customerName = null,
    ) {}
}
