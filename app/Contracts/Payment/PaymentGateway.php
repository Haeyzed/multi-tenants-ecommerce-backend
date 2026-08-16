<?php

declare(strict_types=1);

namespace App\Contracts\Payment;

use App\DTO\Payment\PaymentInitiationRequest;
use App\DTO\Payment\PaymentInitiationResult;
use App\DTO\Payment\PaymentRefundResult;
use App\DTO\Payment\PaymentVerificationResult;
use RuntimeException;

/**
 * Contract for payment gateway drivers.
 */
interface PaymentGateway
{
    /**
     * Stable driver name (e.g. paystack, flutterwave).
     */
    public function name(): string;

    /**
     * Initialize a payment with the provider and return checkout details.
     */
    public function initializePayment(PaymentInitiationRequest $request): PaymentInitiationResult;

    /**
     * Verify a payment by provider reference.
     */
    public function verifyPayment(string $reference): PaymentVerificationResult;

    /**
     * Alias for verifyPayment — used by status polling clients.
     */
    public function getPaymentStatus(string $reference): PaymentVerificationResult;

    /**
     * Whether the driver accepts the given ISO currency code.
     */
    public function supportsCurrency(string $currency): bool;

    /**
     * ISO currency codes this driver accepts.
     *
     * @return list<string>
     */
    public function supportedCurrencies(): array;

    /**
     * Payment methods this driver supports (e.g. card, bank_transfer).
     *
     * @return list<string>
     */
    public function supportedMethods(): array;

    /**
     * Refund a previously successful payment.
     *
     * @throws RuntimeException When refunds are not implemented for the driver.
     */
    public function refundPayment(string $providerTransactionId, ?string $amount = null): bool;

    /**
     * Refund with full gateway response details.
     */
    public function refundPaymentDetailed(
        string $providerTransactionId,
        ?string $amount = null,
        ?string $currency = null,
    ): PaymentRefundResult;
}
