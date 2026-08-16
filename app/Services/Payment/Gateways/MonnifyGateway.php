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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Monnify payment gateway (official Collections API).
 *
 * @see https://developers.monnify.com/docs/collections/quickstart
 */
class MonnifyGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'monnify';
    }

    public function initializePayment(PaymentInitiationRequest $request): PaymentInitiationResult
    {
        $currency = Str::upper($request->currency);

        if (! $this->supportsCurrency($currency)) {
            throw new RuntimeException("Monnify does not support currency [{$currency}].");
        }

        $contractCode = (string) config('payment.drivers.monnify.contract_code');

        if ($contractCode === '') {
            throw new RuntimeException('Monnify contract code is not configured.');
        }

        $redirectUrl = $request->callbackUrl
            ?? (filled(config('payment.drivers.monnify.callback_url'))
                ? (string) config('payment.drivers.monnify.callback_url')
                : null);

        $payload = [
            'amount' => (float) $request->amount,
            'paymentReference' => $request->reference,
            'currencyCode' => $currency,
            'contractCode' => $contractCode,
            'customerEmail' => $request->email,
            'customerName' => $request->customerName ?? $request->email,
            'paymentDescription' => (string) ($request->metadata['order_number'] ?? 'Order payment'),
        ];

        if ($redirectUrl !== null && $redirectUrl !== '') {
            $payload['redirectUrl'] = $redirectUrl;
        }

        try {
            $response = $this->client()
                ->post('/api/v1/merchant/transactions/init-transaction', $payload)
                ->throw()
                ->json();
        } catch (RequestException $exception) {
            throw new RuntimeException(
                'Monnify payment initialization failed: '.$exception->response?->json('responseMessage', $exception->getMessage()),
                previous: $exception,
            );
        }

        /** @var array{requestSuccessful?: bool, responseMessage?: string, responseBody?: array<string, mixed>} $response */
        if (! ($response['requestSuccessful'] ?? false)) {
            throw new RuntimeException('Monnify payment initialization failed: '.($response['responseMessage'] ?? 'Unknown error.'));
        }

        $body = $response['responseBody'] ?? [];

        return new PaymentInitiationResult(
            reference: (string) ($body['paymentReference'] ?? $request->reference),
            authorizationUrl: (string) ($body['checkoutUrl'] ?? ''),
            accessCode: isset($body['transactionReference']) ? (string) $body['transactionReference'] : null,
            provider: $this->name(),
            raw: $response,
        );
    }

    public function verifyPayment(string $reference): PaymentVerificationResult
    {
        try {
            $response = $this->client()
                ->get('/api/v2/merchant/transactions/query', ['paymentReference' => $reference])
                ->throw()
                ->json();
        } catch (RequestException $exception) {
            throw new RuntimeException(
                'Monnify payment verification failed: '.$exception->response?->json('responseMessage', $exception->getMessage()),
                previous: $exception,
            );
        }

        /** @var array{requestSuccessful?: bool, responseMessage?: string, responseBody?: array<string, mixed>} $response */
        $body = $response['responseBody'] ?? [];
        $paymentStatus = Str::upper((string) ($body['paymentStatus'] ?? ''));
        $successful = (bool) ($response['requestSuccessful'] ?? false)
            && in_array($paymentStatus, ['PAID', 'OVERPAID', 'PARTIALLY_PAID'], true);

        $paidAt = null;
        if (! empty($body['paidOn'])) {
            try {
                $paidAt = Carbon::parse((string) $body['paidOn']);
            } catch (\Throwable) {
                $paidAt = null;
            }
        }

        $amount = null;
        if (isset($body['amountPaid'])) {
            $amount = bcadd((string) $body['amountPaid'], '0', 2);
        }

        return new PaymentVerificationResult(
            successful: $successful,
            reference: (string) ($body['paymentReference'] ?? $reference),
            providerTransactionId: isset($body['transactionReference']) ? (string) $body['transactionReference'] : null,
            amount: $amount,
            currency: isset($body['currency']) ? Str::upper((string) $body['currency']) : null,
            paidAt: $paidAt,
            raw: $response,
            message: isset($response['responseMessage']) ? (string) $response['responseMessage'] : null,
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
        $currencies = config('payment.drivers.monnify.currencies', ['NGN']);

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
        $methods = config('payment.methods.monnify', ['card', 'bank_transfer', 'ussd']);

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
        if ($amount === null) {
            return new PaymentRefundResult(
                successful: false,
                message: 'Monnify refunds require an explicit refund amount.',
            );
        }

        $payload = [
            'transactionReference' => $providerTransactionId,
            'refundReference' => 'REF-'.Str::upper(Str::random(16)),
            'refundAmount' => (float) $amount,
            'refundReason' => 'Order refund',
            'customerNote' => 'Refund',
        ];

        try {
            $response = $this->client(withRetry: false)
                ->post('/api/v1/refunds/initiate-refund', $payload)
                ->throw()
                ->json();
        } catch (RequestException $exception) {
            $status = $exception->response?->status();

            if ($status === null || $status >= 500) {
                return new PaymentRefundResult(
                    successful: false,
                    message: $exception->response?->json('responseMessage', $exception->getMessage()),
                    raw: [
                        'exception' => $exception->getMessage(),
                        'status' => $status,
                    ],
                    ambiguous: true,
                );
            }

            return new PaymentRefundResult(
                successful: false,
                message: $exception->response?->json('responseMessage', $exception->getMessage()),
                raw: ['exception' => $exception->getMessage(), 'status' => $status],
            );
        }

        /** @var array{requestSuccessful?: bool, responseMessage?: string, responseBody?: array<string, mixed>} $response */
        $body = $response['responseBody'] ?? [];
        $refundStatus = Str::upper((string) ($body['refundStatus'] ?? ''));
        $successful = (bool) ($response['requestSuccessful'] ?? false)
            && in_array($refundStatus, ['COMPLETED', 'SUCCESS', 'SUCCESSFUL', 'IN_PROGRESS', 'PENDING'], true);

        return new PaymentRefundResult(
            successful: $successful,
            providerRefundId: isset($body['refundReference']) ? (string) $body['refundReference'] : null,
            amount: isset($body['refundAmount']) ? bcadd((string) $body['refundAmount'], '0', 2) : $amount,
            currency: $currency !== null ? Str::upper($currency) : 'NGN',
            raw: $response,
            message: isset($response['responseMessage']) ? (string) $response['responseMessage'] : null,
        );
    }

    protected function client(bool $withRetry = true): PendingRequest
    {
        $token = $this->accessToken();

        $request = Http::baseUrl((string) config('payment.drivers.monnify.base_url', 'https://sandbox.monnify.com'))
            ->withToken($token)
            ->acceptJson()
            ->timeout((int) config('payment.drivers.monnify.timeout', 15))
            ->connectTimeout((int) config('payment.drivers.monnify.connect_timeout', 5));

        if ($withRetry) {
            $request = $request->retry([100, 500, 1000]);
        }

        return $request;
    }

    protected function accessToken(): string
    {
        $apiKey = (string) config('payment.drivers.monnify.api_key');
        $secretKey = (string) config('payment.drivers.monnify.secret_key');

        if ($apiKey === '' || $secretKey === '') {
            throw new RuntimeException('Monnify API key / secret key is not configured.');
        }

        $cacheKey = 'monnify.access_token.'.hash('sha256', $apiKey);
        $minutes = max(1, (int) config('payment.drivers.monnify.token_cache_minutes', 50));

        /** @var string $token */
        $token = Cache::remember($cacheKey, now()->addMinutes($minutes), function () use ($apiKey, $secretKey): string {
            $basic = base64_encode($apiKey.':'.$secretKey);

            try {
                $response = Http::baseUrl((string) config('payment.drivers.monnify.base_url', 'https://sandbox.monnify.com'))
                    ->withHeaders(['Authorization' => 'Basic '.$basic])
                    ->acceptJson()
                    ->timeout((int) config('payment.drivers.monnify.timeout', 15))
                    ->connectTimeout((int) config('payment.drivers.monnify.connect_timeout', 5))
                    ->post('/api/v1/auth/login')
                    ->throw()
                    ->json();
            } catch (RequestException $exception) {
                throw new RuntimeException(
                    'Monnify authentication failed: '.$exception->response?->json('responseMessage', $exception->getMessage()),
                    previous: $exception,
                );
            }

            /** @var array{requestSuccessful?: bool, responseBody?: array{accessToken?: string}} $response */
            $accessToken = $response['responseBody']['accessToken'] ?? null;

            if (! ($response['requestSuccessful'] ?? false) || ! is_string($accessToken) || $accessToken === '') {
                throw new RuntimeException('Monnify authentication failed: missing access token.');
            }

            return $accessToken;
        });

        return $token;
    }
}
