<?php

declare(strict_types=1);

namespace App\Services\Payment\Gateways;

use App\Contracts\Payment\PaymentGateway;
use App\DTO\Payment\PaymentInitiationRequest;
use App\DTO\Payment\PaymentInitiationResult;
use App\DTO\Payment\PaymentRefundResult;
use App\DTO\Payment\PaymentVerificationResult;
use Illuminate\Support\Str;

/**
 * Deterministic fake gateway for automated tests and local development.
 */
class FakePaymentGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'fake';
    }

    public function initializePayment(PaymentInitiationRequest $request): PaymentInitiationResult
    {
        $currency = Str::upper($request->currency);

        if (! $this->supportsCurrency($currency)) {
            return new PaymentInitiationResult(
                reference: $request->reference,
                authorizationUrl: '',
                provider: $this->name(),
                raw: ['error' => "Fake gateway does not support currency [{$currency}]."],
            );
        }

        $base = rtrim((string) config('payment.drivers.fake.authorization_url', 'https://payments.test/fake/authorize'), '/');

        return new PaymentInitiationResult(
            reference: $request->reference,
            authorizationUrl: $base.'?reference='.rawurlencode($request->reference),
            accessCode: 'fake_access_'.$request->reference,
            provider: $this->name(),
            raw: [
                'amount' => $request->amount,
                'currency' => $currency,
                'metadata' => $request->metadata,
            ],
        );
    }

    public function verifyPayment(string $reference): PaymentVerificationResult
    {
        $successful = Str::startsWith($reference, 'FAKE-OK')
            || Str::contains(Str::upper($reference), 'FAKE-OK')
            || Str::contains(Str::upper($reference), 'FAKE-META');

        return new PaymentVerificationResult(
            successful: $successful,
            reference: $reference,
            providerTransactionId: $successful ? 'fake_txn_'.$reference : null,
            amount: $successful ? '100.00' : null,
            currency: $successful ? 'NGN' : null,
            paidAt: $successful ? now() : null,
            raw: ['reference' => $reference, 'successful' => $successful],
            message: $successful ? 'Fake payment successful.' : 'Fake payment not successful.',
        );
    }

    public function getPaymentStatus(string $reference): PaymentVerificationResult
    {
        return $this->verifyPayment($reference);
    }

    public function supportsCurrency(string $currency): bool
    {
        return in_array(Str::upper($currency), $this->supportedCurrencies(), true);
    }

    /**
     * @return list<string>
     */
    public function supportedCurrencies(): array
    {
        /** @var list<string> $currencies */
        $currencies = config('payment.drivers.fake.currencies', ['NGN', 'USD']);

        return array_values(array_map(
            static fn (string $code): string => Str::upper($code),
            $currencies,
        ));
    }

    /**
     * @return list<string>
     */
    public function supportedMethods(): array
    {
        /** @var list<string> $methods */
        $methods = config('payment.methods.fake', ['card', 'bank_transfer']);

        return $methods;
    }

    public function refundPayment(string $providerTransactionId, ?string $amount = null): bool
    {
        return $this->refundPaymentDetailed($providerTransactionId, $amount)->successful;
    }

    public function refundPaymentDetailed(
        string $providerTransactionId,
        ?string $amount = null,
        ?string $currency = null,
    ): PaymentRefundResult {
        return new PaymentRefundResult(
            successful: true,
            providerRefundId: 'fake_refund_'.$providerTransactionId,
            amount: $amount,
            currency: $currency !== null ? Str::upper($currency) : 'NGN',
            raw: [
                'provider_transaction_id' => $providerTransactionId,
                'amount' => $amount,
            ],
            message: 'Fake refund successful.',
        );
    }
}
