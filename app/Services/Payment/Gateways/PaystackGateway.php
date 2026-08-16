<?php

declare(strict_types=1);

namespace App\Services\Payment\Gateways;

use App\Contracts\Payment\PaymentGateway;
use App\DTO\Payment\PaymentInitiationRequest;
use App\DTO\Payment\PaymentInitiationResult;
use App\DTO\Payment\PaymentRefundResult;
use App\DTO\Payment\PaymentVerificationResult;
use Carbon\Carbon;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Paystack payment gateway using Laravel's HTTP client.
 */
class PaystackGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'paystack';
    }

    /**
     * Initialize a Paystack transaction.
     *
     * @throws RuntimeException
     */
    public function initializePayment(PaymentInitiationRequest $request): PaymentInitiationResult
    {
        $currency = Str::upper($request->currency);

        if (! $this->supportsCurrency($currency)) {
            throw new RuntimeException("Paystack does not support currency [{$currency}].");
        }

        $payload = [
            'email' => $request->email,
            'amount' => $this->toMinorUnits($request->amount, $currency),
            'currency' => $currency,
            'reference' => $request->reference,
            'metadata' => $request->metadata,
        ];

        if ($request->callbackUrl !== null) {
            $payload['callback_url'] = $request->callbackUrl;
        } elseif (filled(config('payment.drivers.paystack.callback_url'))) {
            $payload['callback_url'] = config('payment.drivers.paystack.callback_url');
        }

        if ($request->customerName !== null) {
            $payload['metadata']['customer_name'] = $request->customerName;
        }

        try {
            $response = $this->client()
                ->post('/transaction/initialize', $payload)
                ->throw()
                ->json();
        } catch (RequestException $exception) {
            throw new RuntimeException(
                'Paystack payment initialization failed: '.$exception->response?->json('message', $exception->getMessage()),
                previous: $exception,
            );
        }

        /** @var array{status?: bool, message?: string, data?: array<string, mixed>} $response */
        if (! ($response['status'] ?? false)) {
            throw new RuntimeException('Paystack payment initialization failed: '.($response['message'] ?? 'Unknown error.'));
        }

        $data = $response['data'] ?? [];

        return new PaymentInitiationResult(
            reference: (string) ($data['reference'] ?? $request->reference),
            authorizationUrl: (string) ($data['authorization_url'] ?? ''),
            accessCode: isset($data['access_code']) ? (string) $data['access_code'] : null,
            provider: 'paystack',
            raw: $response,
        );
    }

    /**
     * Verify a Paystack transaction by reference.
     *
     * @throws RuntimeException
     */
    public function verifyPayment(string $reference): PaymentVerificationResult
    {
        try {
            $response = $this->client()
                ->get('/transaction/verify/'.rawurlencode($reference))
                ->throw()
                ->json();
        } catch (RequestException $exception) {
            throw new RuntimeException(
                'Paystack payment verification failed: '.$exception->response?->json('message', $exception->getMessage()),
                previous: $exception,
            );
        }

        /** @var array{status?: bool, message?: string, data?: array<string, mixed>} $response */
        $data = $response['data'] ?? [];
        $status = Str::lower((string) ($data['status'] ?? ''));
        $successful = ($response['status'] ?? false) && $status === 'success';

        $paidAt = null;
        if (! empty($data['paid_at'])) {
            $paidAt = Carbon::parse((string) $data['paid_at']);
        } elseif (! empty($data['transaction_date'])) {
            $paidAt = Carbon::parse((string) $data['transaction_date']);
        }

        $amount = null;
        if (isset($data['amount'])) {
            $amount = $this->fromMinorUnits((int) $data['amount'], (string) ($data['currency'] ?? 'NGN'));
        }

        return new PaymentVerificationResult(
            successful: $successful,
            reference: (string) ($data['reference'] ?? $reference),
            providerTransactionId: isset($data['id']) ? (string) $data['id'] : null,
            amount: $amount,
            currency: isset($data['currency']) ? Str::upper((string) $data['currency']) : null,
            paidAt: $paidAt,
            raw: $response,
            message: $response['message'] ?? null,
        );
    }

    public function getPaymentStatus(string $reference): PaymentVerificationResult
    {
        return $this->verifyPayment($reference);
    }

    /**
     * Whether Paystack supports the given currency.
     */
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
        $currencies = config('payment.drivers.paystack.currencies', ['NGN', 'GHS', 'ZAR', 'USD']);

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
        $methods = config('payment.methods.paystack', ['card', 'bank_transfer', 'ussd', 'qr']);

        return $methods;
    }

    /**
     * Refund a previously successful Paystack transaction.
     */
    public function refundPayment(string $providerTransactionId, ?string $amount = null, ?string $currency = null): bool
    {
        return $this->refundPaymentDetailed($providerTransactionId, $amount, $currency)->successful;
    }

    /**
     * Refund with full gateway response details.
     *
     * @param  string|null  $currency  ISO currency used for minor-unit conversion (defaults to NGN).
     */
    public function refundPaymentDetailed(
        string $providerTransactionId,
        ?string $amount = null,
        ?string $currency = null,
    ): PaymentRefundResult {
        $payload = [
            'transaction' => $providerTransactionId,
        ];

        if ($amount !== null) {
            $payload['amount'] = $this->toMinorUnits($amount, $currency ?? 'NGN');
        }

        try {
            $response = $this->client(withRetry: false)
                ->post('/refund', $payload)
                ->throw()
                ->json();
        } catch (RequestException $exception) {
            $status = $exception->response?->status();

            // 5xx / empty response: Paystack may have created the refund. Callers must
            // leave the local refund in Processing and reconcile later.
            if ($status === null || $status >= 500) {
                return new PaymentRefundResult(
                    successful: false,
                    message: $exception->response?->json('message', $exception->getMessage()),
                    raw: [
                        'exception' => $exception->getMessage(),
                        'status' => $status,
                    ],
                    ambiguous: true,
                );
            }

            return new PaymentRefundResult(
                successful: false,
                message: $exception->response?->json('message', $exception->getMessage()),
                raw: ['exception' => $exception->getMessage(), 'status' => $status],
            );
        }

        /** @var array{status?: bool, message?: string, data?: array<string, mixed>} $response */
        $data = $response['data'] ?? [];
        $successful = (bool) ($response['status'] ?? false);

        $refundAmount = null;
        if (isset($data['amount'])) {
            $refundAmount = $this->fromMinorUnits((int) $data['amount'], (string) ($data['currency'] ?? 'NGN'));
        }

        return new PaymentRefundResult(
            successful: $successful,
            providerRefundId: isset($data['id']) ? (string) $data['id'] : null,
            amount: $refundAmount ?? $amount,
            currency: isset($data['currency']) ? Str::upper((string) $data['currency']) : null,
            raw: $response,
            message: isset($response['message']) ? (string) $response['message'] : null,
        );
    }

    /**
     * List Paystack refunds for a provider transaction id.
     *
     * @return list<array<string, mixed>>
     *
     * @throws RuntimeException
     */
    public function listRefundsForTransaction(string $providerTransactionId): array
    {
        try {
            $response = $this->client()
                ->get('/refund', ['transaction' => $providerTransactionId])
                ->throw()
                ->json();
        } catch (RequestException $exception) {
            throw new RuntimeException(
                'Paystack refund lookup failed: '.$exception->response?->json('message', $exception->getMessage()),
                previous: $exception,
            );
        }

        /** @var array{status?: bool, message?: string, data?: list<array<string, mixed>>|array<string, mixed>} $response */
        if (! ($response['status'] ?? false)) {
            throw new RuntimeException('Paystack refund lookup failed: '.($response['message'] ?? 'Unknown error.'));
        }

        $data = $response['data'] ?? [];

        if ($data === []) {
            return [];
        }

        // Paystack may return a single object or a list.
        if (array_is_list($data)) {
            /** @var list<array<string, mixed>> $data */
            return $data;
        }

        /** @var array<string, mixed> $data */
        return [$data];
    }

    /**
     * Build a configured HTTP client for Paystack.
     *
     * Refund POSTs intentionally skip retries — retrying a non-idempotent refund
     * can double-credit the customer when the first attempt actually succeeded.
     */
    protected function client(bool $withRetry = true): PendingRequest
    {
        $secret = (string) config('payment.drivers.paystack.secret_key');

        if ($secret === '') {
            throw new RuntimeException('Paystack secret key is not configured.');
        }

        $request = Http::baseUrl((string) config('payment.drivers.paystack.base_url', 'https://api.paystack.co'))
            ->withToken($secret)
            ->acceptJson()
            ->timeout((int) config('payment.drivers.paystack.timeout', 15))
            ->connectTimeout((int) config('payment.drivers.paystack.connect_timeout', 5));

        if ($withRetry) {
            $request = $request->retry([100, 500, 1000]);
        }

        return $request;
    }

    /**
     * Convert a decimal major-unit amount to the provider's minor units.
     */
    protected function toMinorUnits(string $amount, string $currency): int
    {
        $multiplier = $this->minorUnitMultiplier($currency);

        return (int) bcmul($amount, (string) $multiplier, 0);
    }

    /**
     * Convert provider minor units back to a decimal major-unit string.
     */
    protected function fromMinorUnits(int $amount, string $currency): string
    {
        $multiplier = $this->minorUnitMultiplier($currency);

        return bcdiv((string) $amount, (string) $multiplier, 2);
    }

    /**
     * Resolve the minor-unit multiplier for a currency.
     */
    protected function minorUnitMultiplier(string $currency): int
    {
        return match (Str::upper($currency)) {
            'JPY', 'KRW' => 1,
            default => 100,
        };
    }
}
