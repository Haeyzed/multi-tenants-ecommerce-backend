<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Commerce;

use App\Models\Tenant\InvoiceItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin InvoiceItem
 */
class InvoiceItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var InvoiceItem $item */
        $item = $this->resource;

        return [
            'id' => $item->id,
            'description' => $item->description,
            'quantity' => $item->quantity,
            'unit_price' => $item->unit_price,
            'tax_amount' => $item->tax_amount,
            'subtotal' => $item->subtotal,
            'total' => $item->total,
        ];
    }
}
