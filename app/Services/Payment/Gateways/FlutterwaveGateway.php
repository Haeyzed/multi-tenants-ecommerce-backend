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
 * Flutterwave Standard payment gateway (API v3).
 *
 * @see https://developer.flutterwave.com/v3.0/docs/flutterwave-standard-1
 */
class FlutterwaveGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'flutterwave';
    }

    public function initializePayment(PaymentInitiationRequest $request): PaymentInitiationResult
    {
        $currency = Str::upper($request->currency);

        if (! $this->supportsCurrency($currency)) {
            throw new RuntimeException("Flutterwave does not support currency [{$currency}].");
        }

        $redirectUrl = $request->callbackUrl
            ?? (filled(config('payment.drivers.flutterwave.callback_url'))
                ? (string) config('payment.drivers.flutterwave.callback_url')
                : null);

        if ($redirectUrl === null || $redirectUrl === '') {
            throw new RuntimeException('Flutterwave redirect_url / callback_url is required.');
        }

        $customer = [
            'email' => $request->email,
        ];

        if ($request->customerName !== null && $request->customerName !== '') {
            $customer['name'] = $request->customerName;
        }

        $payload = [
            'tx_ref' => $request->reference,
            'amount' => $request->amount,
            'currency' => $currency,
            'redirect_url' => $redirectUrl,
            'customer' => $customer,
            'customizations' => [
                'title' => (string) config('app.name', 'Payment'),
            ],
            'meta' => $request->metadata,
        ];

        try {
            $response = $this->client()
                ->post('/payments', $payload)
                ->throw()
                ->json();
        } catch (RequestException $exception) {
            throw new RuntimeException(
                'Flutterwave payment initialization failed: '.$exception->response?->json('message', $exception->getMessage()),
                previous: $exception,
            );
        }

        /** @var array{status?: string, message?: string, data?: array<string, mixed>} $response */
        if (Str::lower((string) ($response['status'] ?? '')) !== 'success') {
            throw new RuntimeException('Flutterwave payment initialization failed: '.($response['message'] ?? 'Unknown error.'));
        }

        $data = $response['data'] ?? [];

        return new PaymentInitiationResult(
            reference: $request->reference,
            authorizationUrl: (string) ($data['link'] ?? ''),
            accessCode: null,
            provider: $this->name(),
            raw: $response,
        );
    }

    public function verifyPayment(string $reference): PaymentVerificationResult
    {
        try {
            $response = $this->client()
                ->get('/transactions/verify_by_reference', ['tx_ref' => $reference])
                ->throw()
                ->json();
        } catch (RequestException $exception) {
            throw new RuntimeException(
                'Flutterwave payment verification failed: '.$exception->response?->json('message', $exception->getMessage()),
                previous: $exception,
            );
        }

        /** @var array{status?: string, message?: string, data?: array<string, mixed>} $response */
        $data = $response['data'] ?? [];
        $status = Str::lower((string) ($data['status'] ?? ''));
        $successful = Str::lower((string) ($response['status'] ?? '')) === 'success'
            && in_array($status, ['successful', 'success'], true);

        $paidAt = null;
        if (! empty($data['created_at'])) {
            $paidAt = Carbon::parse((string) $data['created_at']);
        }

        $amount = isset($data['amount']) ? $this->normalizeAmount((string) $data['amount']) : null;

        return new PaymentVerificationResult(
            successful: $successful,
            reference: (string) ($data['tx_ref'] ?? $reference),
            providerTransactionId: isset($data['id']) ? (string) $data['id'] : null,
            amount: $amount,
            currency: isset($data['currency']) ? Str::upper((string) $data['currency']) : null,
            paidAt: $paidAt,
            raw: $response,
            message: isset($response['message']) ? (string) $response['message'] : null,
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
        $currencies = config('payment.drivers.flutterwave.currencies', ['NGN', 'USD', 'GHS', 'KES', 'ZAR']);

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
        $methods = config('payment.methods.flutterwave', ['card', 'bank_transfer', 'ussd', 'mobile_money']);

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
        $payload = [];

        if ($amount !== null) {
            $payload['amount'] = $amount;
        }

        try {
            $response = $this->client(withRetry: false)
                ->post('/transactions/'.rawurlencode($providerTransactionId).'/refund', $payload)
                ->throw()
                ->json();
        } catch (RequestException $exception) {
            $status = $exception->response?->status();

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

        /** @var array{status?: string, message?: string, data?: array<string, mixed>} $response */
        $data = is_array($response['data'] ?? null) ? $response['data'] : [];
        $successful = Str::lower((string) ($response['status'] ?? '')) === 'success';

        return new PaymentRefundResult(
            successful: $successful,
            providerRefundId: isset($data['id']) ? (string) $data['id'] : null,
            amount: isset($data['amount_refunded'])
                ? $this->normalizeAmount((string) $data['amount_refunded'])
                : $amount,
            currency: $currency !== null ? Str::upper($currency) : null,
            raw: $response,
            message: isset($response['message']) ? (string) $response['message'] : null,
        );
    }

    protected function client(bool $withRetry = true): PendingRequest
    {
        $secret = (string) config('payment.drivers.flutterwave.secret_key');

        if ($secret === '') {
            throw new RuntimeException('Flutterwave secret key is not configured.');
        }

        $request = Http::baseUrl((string) config('payment.drivers.flutterwave.base_url', 'https://api.flutterwave.com/v3'))
            ->withToken($secret)
            ->acceptJson()
            ->timeout((int) config('payment.drivers.flutterwave.timeout', 15))
            ->connectTimeout((int) config('payment.drivers.flutterwave.connect_timeout', 5));

        if ($withRetry) {
            $request = $request->retry([100, 500, 1000]);
        }

        return $request;
    }

    protected function normalizeAmount(string $amount): string
    {
        return bcadd($amount, '0', 2);
    }
}
