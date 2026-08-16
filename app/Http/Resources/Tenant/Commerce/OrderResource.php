<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Commerce;

use App\Http\Resources\Tenant\Customer\CustomerResource;
use App\Models\Tenant\Order;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API resource for a sales order.
 *
 * `grand_total` / `amount_due` is the remaining gateway charge (prepaid tenders already
 * applied). `recognized_total` is the economic order total including gift card and
 * store credit snapshots.
 *
 * @mixin Order
 */
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Order $order */
        $order = $this->resource;

        $amountDue = Money::add((string) $order->grand_total, '0');
        $giftCardAmount = Money::add((string) ($order->gift_card_amount ?? '0.00'), '0');
        $storeCreditAmount = Money::add((string) ($order->store_credit_amount ?? '0.00'), '0');
        $recognizedTotal = Money::add(Money::add($amountDue, $giftCardAmount), $storeCreditAmount);

        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'customer_id' => $order->customer_id,
            'sales_channel' => $order->sales_channel instanceof \BackedEnum
                ? $order->sales_channel->value
                : $order->sales_channel,
            'pos_terminal_id' => $order->pos_terminal_id,
            'pos_session_id' => $order->pos_session_id,
            'warehouse_id' => $order->warehouse_id,
            'currency' => $order->currency,
            'status' => $order->status,
            'payment_status' => $order->payment_status,
            'fulfillment_status' => $order->fulfillment_status,
            'subtotal' => Money::add((string) $order->subtotal, '0'),
            'discount_total' => Money::add((string) $order->discount_total, '0'),
            'coupon_id' => $order->coupon_id,
            'coupon_code' => $order->coupon_code,
            'promotion_snapshot' => $order->promotion_snapshot,
            'loyalty_points_earned' => $order->loyalty_points_earned,
            'loyalty_points_redeemed' => $order->loyalty_points_redeemed,
            'tax_total' => Money::add((string) $order->tax_total, '0'),
            'shipping_total' => Money::add((string) $order->shipping_total, '0'),
            'grand_total' => $amountDue,
            'amount_due' => $amountDue,
            'recognized_total' => $recognizedTotal,
            'gift_card_id' => $order->gift_card_id,
            'gift_card_amount' => $giftCardAmount,
            'store_credit_amount' => $storeCreditAmount,
            'shipping_method_id' => $order->shipping_method_id,
            'shipping_address_snapshot' => $order->shipping_address_snapshot,
            'billing_address_snapshot' => $order->billing_address_snapshot,
            'notes' => $order->notes,
            'idempotency_key' => $order->idempotency_key,
            'placed_at' => $order->placed_at,
            'confirmed_at' => $order->confirmed_at,
            'cancelled_at' => $order->cancelled_at,
            'customer' => $this->whenLoaded('customer', fn () => new CustomerResource($order->customer)),
            'shipping_method' => $this->whenLoaded('shippingMethod', function () use ($order) {
                if ($order->shippingMethod === null) {
                    return null;
                }

                return [
                    'id' => $order->shippingMethod->id,
                    'name' => $order->shippingMethod->name,
                    'code' => $order->shippingMethod->code,
                    'amount' => Money::add((string) $order->shippingMethod->amount, '0'),
                ];
            }),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'created_at' => $order->created_at,
            'updated_at' => $order->updated_at,
        ];
    }
}
