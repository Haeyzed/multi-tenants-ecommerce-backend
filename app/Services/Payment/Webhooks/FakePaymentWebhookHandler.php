<?php

declare(strict_types=1);

namespace App\Services\Payment\Webhooks;

use App\Contracts\Payment\PaymentWebhookHandlerInterface;
use Illuminate\Http\Request;

/**
 * Deterministic fake webhook handler for tests.
 */
class FakePaymentWebhookHandler implements PaymentWebhookHandlerInterface
{
    public function provider(): string
    {
        return 'fake';
    }

    public function verifySignature(Request $request): bool
    {
        $expected = (string) $request->header('x-fake-signature', '');

        return $expected === 'fake-ok' || $expected === hash_hmac('sha256', $request->getContent(), 'fake');
    }

    public function eventId(Request $request): ?string
    {
        $id = $request->input('event_id') ?? $request->input('id');

        return $id !== null && $id !== '' ? (string) $id : null;
    }

    public function isSuccessfulCharge(Request $request): bool
    {
        return (bool) $request->input('successful', true);
    }

    public function paymentReference(Request $request): ?string
    {
        $reference = $request->input('reference');

        return is_string($reference) && $reference !== '' ? $reference : null;
    }
}
