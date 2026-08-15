<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Shipping;

use App\Models\Tenant\Shipment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Shipment
 */
class ShipmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Shipment $shipment */
        $shipment = $this->resource;

        return [
            'id' => $shipment->id,
            'order_id' => $shipment->order_id,
            'shipping_method_id' => $shipment->shipping_method_id,
            'tracking_number' => $shipment->tracking_number,
            'carrier' => $shipment->carrier,
            'tracking_url' => $shipment->tracking_url,
            'status' => $shipment->status,
            'shipped_at' => $shipment->shipped_at,
            'delivered_at' => $shipment->delivered_at,
            'notes' => $shipment->notes,
            'shipping_method' => $this->whenLoaded('shippingMethod', fn () => new ShippingMethodResource($shipment->shippingMethod)),
            'created_at' => $shipment->created_at,
            'updated_at' => $shipment->updated_at,
        ];
    }
}
