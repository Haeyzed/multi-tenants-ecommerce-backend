<?php

declare(strict_types=1);

namespace App\Contracts\Payment;

use App\DTO\Payment\PaymentInitiationRequest;
use App\DTO\Payment\PaymentInitiationResult;
use App\DTO\Payment\PaymentVerificationResult;
use RuntimeException;

/**
 * Contract for payment gateway drivers.
 */
interface PaymentGateway
{
    /**
     * Initialize a payment with the provider and return checkout details.
     */
    public function initializePayment(PaymentInitiationRequest $request): PaymentInitiationResult;

    /**
     * Verify a payment by provider reference.
     */
    public function verifyPayment(string $reference): PaymentVerificationResult;

    /**
     * Whether the driver accepts the given ISO currency code.
     */
    public function supportsCurrency(string $currency): bool;

    /**
     * Refund a previously successful payment.
     *
     * @throws RuntimeException When refunds are not implemented for the driver.
     */
    public function refundPayment(string $providerTransactionId, ?string $amount = null): bool;
}
