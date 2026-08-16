<?php

declare(strict_types=1);

namespace App\Services\Payment\Webhooks;

use App\Contracts\Payment\PaymentWebhookHandlerInterface;
use Illuminate\Http\Request;

/**
 * Paystack charge webhook signature verification and payload extraction.
 */
class PaystackPaymentWebhookHandler implements PaymentWebhookHandlerInterface
{
    public function provider(): string
    {
        return 'paystack';
    }

    public function verifySignature(Request $request): bool
    {
        $secret = (string) config('payment.drivers.paystack.webhook_secret');

        if ($secret === '') {
            return false;
        }

        $signature = (string) $request->header('x-paystack-signature', '');
        $computed = hash_hmac('sha512', $request->getContent(), $secret);

        return $signature !== '' && hash_equals($computed, $signature);
    }

    public function eventId(Request $request): ?string
    {
        $data = $request->input('data');

        if (is_array($data) && isset($data['id'])) {
            return (string) $data['id'];
        }

        $event = $request->input('event');
        $reference = $this->paymentReference($request);

        if (is_string($event) && $event !== '' && $reference !== null) {
            return $event.':'.$reference;
        }

        $raw = $request->getContent();

        return $raw !== '' ? hash('sha256', $raw) : null;
    }

    public function isSuccessfulCharge(Request $request): bool
    {
        return $request->input('event') === 'charge.success';
    }

    public function paymentReference(Request $request): ?string
    {
        $reference = $request->input('data.reference');

        return is_string($reference) && $reference !== '' ? $reference : null;
    }
}
