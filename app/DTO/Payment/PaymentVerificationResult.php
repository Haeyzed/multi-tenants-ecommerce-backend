<?php

declare(strict_types=1);

namespace App\DTO\Payment;

use Carbon\CarbonInterface;

/**
 * Result returned after verifying a payment with a gateway.
 */
readonly class PaymentVerificationResult
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public bool $successful,
        public string $reference,
        public ?string $providerTransactionId = null,
        public ?string $amount = null,
        public ?string $currency = null,
        public ?CarbonInterface $paidAt = null,
        public array $raw = [],
        public ?string $message = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'successful' => $this->successful,
            'reference' => $this->reference,
            'provider_transaction_id' => $this->providerTransactionId,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'paid_at' => $this->paidAt?->toIso8601String(),
            'message' => $this->message,
        ];
    }
}
