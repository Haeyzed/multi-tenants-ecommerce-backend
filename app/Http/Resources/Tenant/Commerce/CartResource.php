<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Commerce;

use App\Models\Tenant\Cart;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API resource for a shopping cart with totals.
 *
 * @mixin Cart
 */
class CartResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Cart $cart */
        $cart = $this->resource;

        $subtotal = '0.00';

        if ($cart->relationLoaded('items')) {
            foreach ($cart->items as $item) {
                $subtotal = Money::add($subtotal, (string) $item->subtotal);
            }
        }

        return [
            'id' => $cart->id,
            'customer_id' => $cart->customer_id,
            'currency' => $cart->currency,
            'status' => $cart->status,
            'expires_at' => $cart->expires_at,
            'items' => CartItemResource::collection($this->whenLoaded('items')),
            'totals' => [
                'subtotal' => $subtotal,
                'discount_total' => '0.00',
                'tax_total' => '0.00',
                'shipping_total' => '0.00',
                'grand_total' => $subtotal,
            ],
            'created_at' => $cart->created_at,
            'updated_at' => $cart->updated_at,
        ];
    }
}
