<?php

declare(strict_types=1);

namespace App\Services\Payment\Gateways;

use App\Contracts\Payment\PaymentGateway;
use App\DTO\Payment\PaymentInitiationRequest;
use App\DTO\Payment\PaymentInitiationResult;
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

    /**
     * Whether Paystack supports the given currency.
     */
    public function supportsCurrency(string $currency): bool
    {
        /** @var list<string> $currencies */
        $currencies = config('payment.drivers.paystack.currencies', ['NGN', 'GHS', 'ZAR', 'USD']);

        $supported = array_map(static fn (string $code): string => Str::upper($code), $currencies);

        return in_array(Str::upper($currency), $supported, true);
    }

    /**
     * Refunds are not implemented for the Paystack driver yet.
     *
     * @throws RuntimeException
     */
    public function refundPayment(string $providerTransactionId, ?string $amount = null): bool
    {
        throw new RuntimeException('Refunds are not implemented for this driver.');
    }

    /**
     * Build a configured HTTP client for Paystack.
     */
    protected function client(): PendingRequest
    {
        $secret = (string) config('payment.drivers.paystack.secret_key');

        if ($secret === '') {
            throw new RuntimeException('Paystack secret key is not configured.');
        }

        return Http::baseUrl((string) config('payment.drivers.paystack.base_url', 'https://api.paystack.co'))
            ->withToken($secret)
            ->acceptJson()
            ->timeout((int) config('payment.drivers.paystack.timeout', 15))
            ->connectTimeout((int) config('payment.drivers.paystack.connect_timeout', 5))
            ->retry([100, 500, 1000]);
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
