<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Commerce;

use App\Http\Resources\Tenant\Customer\CustomerResource;
use App\Models\Tenant\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API resource for a sales order.
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

        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'customer_id' => $order->customer_id,
            'currency' => $order->currency,
            'status' => $order->status,
            'payment_status' => $order->payment_status,
            'fulfillment_status' => $order->fulfillment_status,
            'subtotal' => $order->subtotal,
            'discount_total' => $order->discount_total,
            'coupon_id' => $order->coupon_id,
            'coupon_code' => $order->coupon_code,
            'promotion_snapshot' => $order->promotion_snapshot,
            'tax_total' => $order->tax_total,
            'shipping_total' => $order->shipping_total,
            'grand_total' => $order->grand_total,
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
                    'amount' => $order->shippingMethod->amount,
                ];
            }),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'created_at' => $order->created_at,
            'updated_at' => $order->updated_at,
        ];
    }
}
