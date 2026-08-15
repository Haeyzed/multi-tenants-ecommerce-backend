<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Commerce;

use App\Models\Tenant\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Invoice
 */
class InvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Invoice $invoice */
        $invoice = $this->resource;

        return [
            'id' => $invoice->id,
            'order_id' => $invoice->order_id,
            'customer_id' => $invoice->customer_id,
            'invoice_number' => $invoice->invoice_number,
            'status' => $invoice->status->value,
            'currency' => $invoice->currency,
            'subtotal' => $invoice->subtotal,
            'discount_total' => $invoice->discount_total,
            'tax_total' => $invoice->tax_total,
            'shipping_total' => $invoice->shipping_total,
            'grand_total' => $invoice->grand_total,
            'issued_at' => $invoice->issued_at,
            'items' => InvoiceItemResource::collection($this->whenLoaded('items')),
            'created_at' => $invoice->created_at,
            'updated_at' => $invoice->updated_at,
        ];
    }
}
