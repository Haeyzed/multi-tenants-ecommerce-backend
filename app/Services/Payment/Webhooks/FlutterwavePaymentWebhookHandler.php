<?php

declare(strict_types=1);

namespace App\Services\Payment\Webhooks;

use App\Contracts\Payment\PaymentWebhookHandlerInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Flutterwave webhook verification (verif-hash and flutterwave-signature).
 *
 * @see https://developer.flutterwave.com/v3.0/docs/webhooks
 */
class FlutterwavePaymentWebhookHandler implements PaymentWebhookHandlerInterface
{
    public function provider(): string
    {
        return 'flutterwave';
    }

    public function verifySignature(Request $request): bool
    {
        $secretHash = (string) config('payment.drivers.flutterwave.secret_hash');

        if ($secretHash === '') {
            return false;
        }

        $verifHash = (string) $request->header('verif-hash', '');

        if ($verifHash !== '' && hash_equals($secretHash, $verifHash)) {
            return true;
        }

        $signature = (string) $request->header('flutterwave-signature', '');

        if ($signature === '') {
            return false;
        }

        $computed = hash_hmac('sha256', $request->getContent(), $secretHash);

        return hash_equals($computed, $signature);
    }

    public function eventId(Request $request): ?string
    {
        $id = $request->input('data.id') ?? $request->input('id');

        if ($id !== null && $id !== '') {
            return (string) $id;
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
        $event = Str::lower((string) $request->input('event', ''));

        if (in_array($event, ['charge.completed', 'charge.success'], true)) {
            $status = Str::lower((string) $request->input('data.status', ''));

            return in_array($status, ['successful', 'success'], true);
        }

        $status = Str::lower((string) $request->input('data.status', $request->input('status', '')));

        return in_array($status, ['successful', 'success'], true)
            && $this->paymentReference($request) !== null;
    }

    public function paymentReference(Request $request): ?string
    {
        $candidates = [
            $request->input('data.tx_ref'),
            $request->input('tx_ref'),
            $request->input('data.txRef'),
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                return $candidate;
            }
        }

        return null;
    }
}
