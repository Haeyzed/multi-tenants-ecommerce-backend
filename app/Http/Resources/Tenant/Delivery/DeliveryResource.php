<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Delivery;

use App\Http\Resources\Tenant\Driver\DriverResource;
use App\Models\Tenant\Delivery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API resource for deliveries.
 *
 * @mixin Delivery
 */
class DeliveryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Delivery $delivery */
        $delivery = $this->resource;

        return [
            'id' => $delivery->id,
            'order_id' => $delivery->order_id,
            'shipment_id' => $delivery->shipment_id,
            'driver_id' => $delivery->driver_id,
            'status' => $delivery->status?->value,
            'assigned_at' => $delivery->assigned_at,
            'accepted_at' => $delivery->accepted_at,
            'picked_up_at' => $delivery->picked_up_at,
            'out_for_delivery_at' => $delivery->out_for_delivery_at,
            'delivered_at' => $delivery->delivered_at,
            'failed_at' => $delivery->failed_at,
            'cancelled_at' => $delivery->cancelled_at,
            'failure_reason' => $delivery->failure_reason,
            'notes' => $delivery->notes,
            'driver' => new DriverResource($this->whenLoaded('driver')),
            'created_at' => $delivery->created_at,
            'updated_at' => $delivery->updated_at,
        ];
    }
}
