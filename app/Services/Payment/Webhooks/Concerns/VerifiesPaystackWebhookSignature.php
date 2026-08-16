<?php

declare(strict_types=1);

namespace App\Services\Payment\Webhooks\Concerns;

use Illuminate\Http\Request;

/**
 * Shared Paystack HMAC signature verification for landlord and tenant webhook handlers.
 *
 * Prefer webhook_secret; fall back to secret_key (common Paystack dashboard setup).
 */
trait VerifiesPaystackWebhookSignature
{
    protected function paystackWebhookSecret(): string
    {
        $webhookSecret = (string) config('payment.drivers.paystack.webhook_secret', '');

        if ($webhookSecret !== '') {
            return $webhookSecret;
        }

        return (string) config('payment.drivers.paystack.secret_key', '');
    }

    protected function paystackSignatureIsValid(Request $request): bool
    {
        $secret = $this->paystackWebhookSecret();

        if ($secret === '') {
            return false;
        }

        $signature = (string) $request->header('x-paystack-signature', '');
        $computed = hash_hmac('sha512', $request->getContent(), $secret);

        return $signature !== '' && hash_equals($computed, $signature);
    }
}
