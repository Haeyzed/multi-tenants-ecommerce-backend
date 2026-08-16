<?php

declare(strict_types=1);

namespace App\Services\Payment\Webhooks;

use App\Contracts\Payment\PaymentWebhookHandlerInterface;
use App\Services\Payment\Webhooks\Concerns\VerifiesPaystackWebhookSignature;
use Illuminate\Http\Request;

/**
 * Tenant order-payment Paystack webhook verifier (signature + payload helpers).
 *
 * Landlord subscription webhooks use {@see PaystackWebhookHandler} on the central domain.
 * Both share {@see VerifiesPaystackWebhookSignature}; they must not be merged — different
 * databases, idempotency tables, and business side effects.
 */
class PaystackPaymentWebhookHandler implements PaymentWebhookHandlerInterface
{
    use VerifiesPaystackWebhookSignature;

    public function provider(): string
    {
        return 'paystack';
    }

    public function verifySignature(Request $request): bool
    {
        return $this->paystackSignatureIsValid($request);
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
