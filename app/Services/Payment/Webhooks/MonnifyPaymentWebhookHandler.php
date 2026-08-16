<?php

declare(strict_types=1);

namespace App\Services\Payment\Webhooks;

use App\Contracts\Payment\PaymentWebhookHandlerInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Monnify SUCCESSFUL_TRANSACTION webhook verification.
 *
 * @see https://developers.monnify.com/docs/webhooks
 */
class MonnifyPaymentWebhookHandler implements PaymentWebhookHandlerInterface
{
    public function provider(): string
    {
        return 'monnify';
    }

    public function verifySignature(Request $request): bool
    {
        $secret = (string) config('payment.drivers.monnify.secret_key');

        if ($secret === '') {
            return false;
        }

        $signature = (string) $request->header('monnify-signature', '');

        if ($signature === '') {
            return false;
        }

        $computed = hash_hmac('sha512', $request->getContent(), $secret);

        return hash_equals($computed, $signature);
    }

    public function eventId(Request $request): ?string
    {
        $transactionReference = $request->input('eventData.transactionReference');

        if (is_string($transactionReference) && $transactionReference !== '') {
            return $transactionReference;
        }

        $raw = $request->getContent();

        return $raw !== '' ? hash('sha256', $raw) : null;
    }

    public function isSuccessfulCharge(Request $request): bool
    {
        $eventType = Str::upper((string) $request->input('eventType', ''));

        if ($eventType !== 'SUCCESSFUL_TRANSACTION') {
            return false;
        }

        $status = Str::upper((string) $request->input('eventData.paymentStatus', ''));

        return in_array($status, ['PAID', 'OVERPAID', 'PARTIALLY_PAID', ''], true);
    }

    public function paymentReference(Request $request): ?string
    {
        $reference = $request->input('eventData.paymentReference');

        return is_string($reference) && $reference !== '' ? $reference : null;
    }
}
