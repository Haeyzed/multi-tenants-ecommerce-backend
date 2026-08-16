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
        $refundable = $this->refundableAmount($payment);

        if (bccomp($requestedAmount, '0', 2) <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Refund amount must be greater than zero.',
            ]);
        }

        if (bccomp($requestedAmount, $refundable, 2) > 0) {
            throw ValidationException::withMessages([
                'amount' => 'Refund amount exceeds the refundable balance.',
            ]);
        }

        $reference = 'REF-'.$order->order_number.'-'.uniqid();
        $isFullRefund = bccomp($requestedAmount, $refundable, 2) === 0;

        return DB::transaction(function () use ($order, $payment, $data, $requestedAmount, $reference, $isFullRefund): Refund {
            $refund = Refund::query()->create([
                'order_id' => $order->id,
                'order_payment_id' => $payment->id,
                'amount' => $requestedAmount,
                'currency' => $payment->currency,
                'reference' => $reference,
                'status' => RefundStatus::Processing,
                'reason' => $data['reason'] ?? null,
            ]);

            event(new RefundInitiated($refund));

            $gateway = $this->paymentManager->driver($payment->gateway);
            $providerTransactionId = $payment->provider_transaction_id;

            if ($providerTransactionId === null || $providerTransactionId === '') {
                throw ValidationException::withMessages([
                    'payment' => 'Payment has no provider transaction id for refund.',
                ]);
            }

            $result = $gateway instanceof PaystackGateway
                ? $gateway->refundPaymentDetailed(
                    $providerTransactionId,
                    $requestedAmount,
                    (string) $payment->currency,
                )
                : new PaymentRefundResult(
                    successful: $gateway->refundPayment($providerTransactionId, $requestedAmount),
                );

            if (! $result->successful) {
                $refund->status = RefundStatus::Failed;
                $refund->metadata = ['gateway' => $result->raw, 'message' => $result->message];
                $refund->save();

                throw ValidationException::withMessages([
                    'refund' => $result->message ?? 'Gateway refund failed.',
                ]);
            }

            $refund->status = RefundStatus::Completed;
            $refund->provider_refund_id = $result->providerRefundId;
            $refund->processed_at = now();
            $refund->metadata = ['gateway' => $result->raw];
            $refund->save();

            $remaining = $this->refundableAmount($payment->fresh() ?? $payment);

            if (bccomp($remaining, '0', 2) === 0) {
                $order->payment_status = OrderPaymentStatus::Refunded;
            } else {
                $order->payment_status = OrderPaymentStatus::PartiallyRefunded;
            }
            $order->save();

            if ($isFullRefund) {
                $this->accounting->postRefund($order);
                $this->restoreAlternativeFunding($order);
            } else {
                $this->accounting->postPartialRefund($order, $requestedAmount);
            }

            $refund = $refund->fresh(['order', 'payment']) ?? $refund;
            event(new RefundCompleted($refund));

            return $refund;
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

    protected function refundableAmount(OrderPayment $payment): string
    {
        $refunded = Refund::query()
            ->where('order_payment_id', $payment->id)
            ->where('status', RefundStatus::Completed)
            ->sum('amount');

        return Money::sub((string) $payment->amount, (string) $refunded);
    }
}
