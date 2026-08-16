<?php

declare(strict_types=1);

namespace App\Services\Payment\Gateways;

use App\Contracts\Payment\PaymentGateway;
use App\DTO\Payment\PaymentInitiationRequest;
use App\DTO\Payment\PaymentInitiationResult;
use App\DTO\Payment\PaymentRefundResult;
use App\DTO\Payment\PaymentVerificationResult;
use RuntimeException;

/**
 * Moniepoint payment gateway scaffold.
 *
 * Live API calls are intentionally disabled until official Moniepoint API
 * documentation and production credentials are provided. Configure stubs via
 * `payment.drivers.moniepoint` / MONIEPOINT_* env vars.
 */
class MoniepointGateway implements PaymentGateway
{
    private const NOT_CONFIGURED = 'Moniepoint live API not configured: provide official docs and credentials.';

    public function name(): string
    {
        return 'moniepoint';
    }

    public function initializePayment(PaymentInitiationRequest $request): PaymentInitiationResult
    {
        throw new RuntimeException(self::NOT_CONFIGURED);
    }

    public function verifyPayment(string $reference): PaymentVerificationResult
    {
        return new PaymentVerificationResult(
            successful: false,
            reference: $reference,
            message: self::NOT_CONFIGURED,
            raw: ['error' => self::NOT_CONFIGURED],
        );
    }

    public function getPaymentStatus(string $reference): PaymentVerificationResult
    {
        return $this->verifyPayment($reference);
    }

    public function supportsCurrency(string $currency): bool
    {
        return in_array(strtoupper($currency), $this->supportedCurrencies(), true);
    }

    /**
     * @return list<string>
     */
    public function supportedCurrencies(): array
    {
        /** @var list<string> $currencies */
        $currencies = config('payment.drivers.moniepoint.currencies', ['NGN']);

        return array_values(array_map(
            static fn (string $code): string => strtoupper($code),
            $currencies,
        ));
    }

    /**
     * @return list<string>
     */
    public function supportedMethods(): array
    {
        /** @var list<string> $methods */
        $methods = config('payment.methods.moniepoint', ['card', 'bank_transfer']);

        return $methods;
    }

    public function refundPayment(string $providerTransactionId, ?string $amount = null): bool
    {
        return false;
    }

    public function refundPaymentDetailed(
        string $providerTransactionId,
        ?string $amount = null,
        ?string $currency = null,
    ): PaymentRefundResult {
        return new PaymentRefundResult(
            successful: false,
            amount: $amount,
            currency: $currency !== null ? strtoupper($currency) : null,
            message: self::NOT_CONFIGURED,
            raw: ['error' => self::NOT_CONFIGURED],
        );
    }
}
