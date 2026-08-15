<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Commerce;

use App\Models\Tenant\OrderReturn;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OrderReturn
 */
class OrderReturnResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var OrderReturn $return */
        $return = $this->resource;

        return [
            'id' => $return->id,
            'return_number' => $return->return_number,
            'order_id' => $return->order_id,
            'customer_id' => $return->customer_id,
            'seller_id' => $return->seller_id,
            'status' => $return->status,
            'reason' => $return->reason,
            'customer_note' => $return->customer_note,
            'admin_note' => $return->admin_note,
            'refund_id' => $return->refund_id,
            'requested_at' => $return->requested_at,
            'approved_at' => $return->approved_at,
            'rejected_at' => $return->rejected_at,
            'received_at' => $return->received_at,
            'completed_at' => $return->completed_at,
            'items' => OrderReturnItemResource::collection($this->whenLoaded('items')),
            'created_at' => $return->created_at,
            'updated_at' => $return->updated_at,
        ];
    }
}
