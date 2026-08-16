<?php

declare(strict_types=1);

namespace App\Services\Tenant\Commerce;

use App\DTO\Payment\PaymentRefundResult;
use App\Enums\Tenant\Commerce\GiftCardTransactionType;
use App\Enums\Tenant\Commerce\OrderPaymentRecordStatus;
use App\Enums\Tenant\Commerce\OrderPaymentStatus;
use App\Enums\Tenant\Commerce\RefundStatus;
use App\Enums\Tenant\Commerce\StoreCreditTransactionType;
use App\Events\RefundCompleted;
use App\Events\RefundInitiated;
use App\Models\Tenant\GiftCard;
use App\Models\Tenant\GiftCardTransaction;
use App\Models\Tenant\Order;
use App\Models\Tenant\OrderPayment;
use App\Models\Tenant\Refund;
use App\Models\Tenant\StoreCreditTransaction;
use App\Services\Payment\Gateways\PaystackGateway;
use App\Services\Payment\PaymentManager;
use App\Services\Tenant\Accounting\AccountingService;
use App\Support\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Process full and partial payment refunds.
 */
class RefundService
{
    public function __construct(
        private readonly PaymentManager $paymentManager,
        private readonly AccountingService $accounting,
        private readonly GiftCardService $giftCards,
        private readonly StoreCreditService $storeCredit,
    ) {}

    /**
     * @param  array{order_id?: int|null, status?: string|null, per_page?: int|null}  $params
     * @return LengthAwarePaginator<int, Refund>
     */
    public function list(array $params = []): LengthAwarePaginator
    {
        $query = Refund::query()->with(['order', 'payment'])->latest('id');

        if (! empty($params['order_id'])) {
            $query->where('order_id', (int) $params['order_id']);
        }

        if (! empty($params['status'])) {
            $query->where('status', $params['status']);
        }

        return $query->paginate(max(1, min((int) ($params['per_page'] ?? 15), 100)));
    }

    /**
     * @param  array{amount?: string|null, reason?: string|null}  $data
     */
    public function create(Order $order, OrderPayment $payment, array $data = []): Refund
    {
        if ($payment->order_id !== $order->id) {
            throw ValidationException::withMessages([
                'payment' => 'Payment does not belong to this order.',
            ]);
        }

        if ($payment->status !== OrderPaymentRecordStatus::Successful) {
            throw ValidationException::withMessages([
                'payment' => 'Only successful payments can be refunded.',
            ]);
        }

        $requestedAmount = isset($data['amount']) ? (string) $data['amount'] : (string) $payment->amount;

        if (bccomp($requestedAmount, '0', 2) <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Refund amount must be greater than zero.',
            ]);
        }

        $providerTransactionId = $payment->provider_transaction_id;

        if ($providerTransactionId === null || $providerTransactionId === '') {
            throw ValidationException::withMessages([
                'payment' => 'Payment has no provider transaction id for refund.',
            ]);
        }

        $reference = 'REF-'.$order->order_number.'-'.uniqid();

        /** @var array{refund: Refund, is_full_refund: bool} $claim */
        $claim = DB::transaction(function () use ($order, $payment, $data, $requestedAmount, $reference): array {
            /** @var OrderPayment $lockedPayment */
            $lockedPayment = OrderPayment::query()
                ->whereKey($payment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()
                ->whereKey($order->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $refundable = $this->refundableAmount($lockedPayment);

            if (bccomp($requestedAmount, $refundable, 2) > 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Refund amount exceeds the refundable balance.',
                ]);
            }

            $isFullRefund = bccomp($requestedAmount, $refundable, 2) === 0;

            $refund = Refund::query()->create([
                'order_id' => $lockedOrder->id,
                'order_payment_id' => $lockedPayment->id,
                'amount' => $requestedAmount,
                'currency' => $lockedPayment->currency,
                'reference' => $reference,
                'status' => RefundStatus::Processing,
                'reason' => $data['reason'] ?? null,
            ]);

            event(new RefundInitiated($refund));

            return [
                'refund' => $refund,
                'is_full_refund' => $isFullRefund,
            ];
        });

        $refund = $claim['refund'];
        $isFullRefund = $claim['is_full_refund'];

        try {
            $gateway = $this->paymentManager->driver($payment->gateway);

            $result = $gateway instanceof PaystackGateway
                ? $gateway->refundPaymentDetailed(
                    $providerTransactionId,
                    $requestedAmount,
                    (string) $payment->currency,
                )
                : new PaymentRefundResult(
                    successful: $gateway->refundPayment($providerTransactionId, $requestedAmount),
                );
        } catch (Throwable $exception) {
            $this->markRefundFailed($refund, [
                'exception' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        if (! $result->successful) {
            $this->markRefundFailed($refund, [
                'gateway' => $result->raw,
                'message' => $result->message,
            ]);

            throw ValidationException::withMessages([
                'refund' => $result->message ?? 'Gateway refund failed.',
            ]);
        }

        return DB::transaction(function () use ($refund, $payment, $order, $requestedAmount, $isFullRefund, $result): Refund {
            /** @var Refund $lockedRefund */
            $lockedRefund = Refund::query()->whereKey($refund->getKey())->lockForUpdate()->firstOrFail();

            if ($lockedRefund->status === RefundStatus::Completed) {
                return $lockedRefund->load(['order', 'payment']);
            }

            /** @var OrderPayment $lockedPayment */
            $lockedPayment = OrderPayment::query()
                ->whereKey($payment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()
                ->whereKey($order->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $lockedRefund->status = RefundStatus::Completed;
            $lockedRefund->provider_refund_id = $result->providerRefundId;
            $lockedRefund->processed_at = now();
            $lockedRefund->metadata = ['gateway' => $result->raw];
            $lockedRefund->save();

            $remaining = $this->refundableAmount($lockedPayment);

            if (bccomp($remaining, '0', 2) === 0) {
                $lockedOrder->payment_status = OrderPaymentStatus::Refunded;
            } else {
                $lockedOrder->payment_status = OrderPaymentStatus::PartiallyRefunded;
            }
            $lockedOrder->save();

            if ($isFullRefund) {
                $this->accounting->postRefund($lockedOrder);
                $this->restoreAlternativeFunding($lockedOrder);
            } else {
                $this->accounting->postPartialRefund($lockedOrder, $requestedAmount);
            }

            $lockedRefund = $lockedRefund->fresh(['order', 'payment']) ?? $lockedRefund;
            event(new RefundCompleted($lockedRefund));

            return $lockedRefund;
        });
    }

    public function show(Refund $refund): Refund
    {
        return $refund->load(['order', 'payment']);
    }

    /**
     * Return the prepaid portions of a mixed-tender order after a gateway refund completes.
     *
     * A gift card + Paystack order is settled in two places: the cash portion sits on the
     * OrderPayment and is reversed by the gateway, while the prepaid portion only exists as
     * the `gift_card_amount` / `store_credit_amount` snapshot written at checkout. Because
     * the gateway can never return more than it took, those snapshots must be restored
     * separately — and the gift card portion is restored first, back to the originating
     * `gift_card_id`, before any store credit is returned to the customer's wallet. That
     * ordering means the customer regains the exact tender they paid with, in the same
     * order the tenders were applied at checkout.
     *
     * Restoration runs once per order: an existing `refund_restore` gift card entry or
     * store credit entry referencing the order short-circuits repeat calls, so a second
     * full refund against another payment cannot double-credit the customer. Orders funded
     * entirely by the gateway are a no-op.
     *
     * @return array{gift_card: string, store_credit: string}
     */
    public function restoreAlternativeFunding(Order $order): array
    {
        $restored = ['gift_card' => '0.00', 'store_credit' => '0.00'];

        if (Schema::hasColumn('orders', 'gift_card_amount')) {
            $giftCardAmount = Money::add((string) ($order->gift_card_amount ?? '0.00'), '0');

            if ($order->gift_card_id !== null && bccomp($giftCardAmount, '0', 2) > 0 && ! $this->giftCardAlreadyRestored($order)) {
                $giftCard = GiftCard::query()->find($order->gift_card_id);

                if ($giftCard !== null) {
                    $this->giftCards->restoreFromRefund(
                        $giftCard,
                        $giftCardAmount,
                        $order,
                        'Refund restored for order '.$order->order_number,
                    );

                    $restored['gift_card'] = $giftCardAmount;
                }
            }
        }

        if (Schema::hasColumn('orders', 'store_credit_amount')) {
            $storeCreditAmount = Money::add((string) ($order->store_credit_amount ?? '0.00'), '0');

            if (bccomp($storeCreditAmount, '0', 2) > 0 && ! $this->storeCreditAlreadyRestored($order)) {
                $this->storeCredit->restoreForOrder($order, $storeCreditAmount);
                $restored['store_credit'] = $storeCreditAmount;
            }
        }

        return $restored;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    protected function markRefundFailed(Refund $refund, array $metadata): void
    {
        $refund->status = RefundStatus::Failed;
        $refund->metadata = $metadata;
        $refund->save();
    }

    protected function giftCardAlreadyRestored(Order $order): bool
    {
        return GiftCardTransaction::query()
            ->where('order_id', $order->id)
            ->where('type', GiftCardTransactionType::RefundRestore)
            ->exists();
    }

    protected function storeCreditAlreadyRestored(Order $order): bool
    {
        return StoreCreditTransaction::query()
            ->where('reference_type', $order->getMorphClass())
            ->where('reference_id', $order->getKey())
            ->where('type', StoreCreditTransactionType::Refund)
            ->exists();
    }

    /**
     * Remaining refundable amount excluding completed and in-flight processing refunds.
     */
    protected function refundableAmount(OrderPayment $payment): string
    {
        $refunded = Refund::query()
            ->where('order_payment_id', $payment->id)
            ->whereIn('status', [RefundStatus::Completed, RefundStatus::Processing])
            ->sum('amount');

        return Money::sub((string) $payment->amount, (string) $refunded);
    }
}
