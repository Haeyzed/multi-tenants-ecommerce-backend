<?php

declare(strict_types=1);

namespace App\DTO\Payment;

/**
 * Result returned after a payment is initialized with a gateway.
 */
readonly class PaymentInitiationResult
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $reference,
        public string $authorizationUrl,
        public ?string $accessCode = null,
        public string $provider = 'paystack',
        public array $raw = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'reference' => $this->reference,
            'authorization_url' => $this->authorizationUrl,
            'access_code' => $this->accessCode,
            'provider' => $this->provider,
        ];
    }
}
