<?php

declare(strict_types=1);

namespace App\Services\Tenant\Shipping;

use App\Enums\Tenant\Commerce\ShipmentStatus;
use App\Events\ShipmentDelivered;
use App\Events\ShipmentShipped;
use App\Models\Tenant\Order;
use App\Models\Tenant\Shipment;
use App\Models\Tenant\ShippingMethod;
use App\Services\Shipping\ShippingCarrierManager;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Create and transition shipments for orders.
 */
class ShipmentService
{
    public function __construct(
        private readonly ShippingCarrierManager $carriers,
    ) {}

    /**
     * @param  array{order_id?: int|null, status?: string|null, per_page?: int|null}  $params
     * @return LengthAwarePaginator<int, Shipment>
     */
    public function list(array $params = []): LengthAwarePaginator
    {
        $query = Shipment::query()
            ->with(['order', 'shippingMethod'])
            ->latest('id');

        if (! empty($params['order_id'])) {
            $query->where('order_id', (int) $params['order_id']);
        }

        if (! empty($params['status'])) {
            $query->where('status', $params['status']);
        }

        return $query->paginate($this->perPage($params));
    }

    /**
     * @param  array{
     *     shipping_method_id?: int|null,
     *     tracking_number?: string|null,
     *     carrier?: string|null,
     *     tracking_url?: string|null,
     *     notes?: string|null,
     *     status?: string|null
     * }  $data
     */
    public function create(Order $order, array $data = []): Shipment
    {
        $shippingMethod = null;
        if (! empty($data['shipping_method_id'])) {
            $shippingMethod = ShippingMethod::query()->find((int) $data['shipping_method_id']);
        } elseif ($order->shipping_method_id !== null) {
            $shippingMethod = $order->shippingMethod;
        }

        $carrierData = [
            'tracking_number' => $data['tracking_number'] ?? null,
            'carrier' => $data['carrier'] ?? null,
            'tracking_url' => $data['tracking_url'] ?? null,
        ];

        if (
            config('shipping.use_carriers')
            && $shippingMethod !== null
            && empty($carrierData['tracking_number'])
        ) {
            $carrier = $this->carriers->forMethodCode($shippingMethod->code)
                ?? $this->carriers->driver();

            $result = $carrier->createShipment([
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'shipping_method_code' => $shippingMethod->code,
                'shipping_address' => $order->shipping_address_snapshot,
            ]);

            if ($result->successful) {
                $carrierData['tracking_number'] = $result->trackingNumber;
                $carrierData['carrier'] = $result->carrier;
                $carrierData['tracking_url'] = $result->trackingUrl;
            }
        }

        return Shipment::query()->create([
            'order_id' => $order->id,
            'shipping_method_id' => $data['shipping_method_id'] ?? $order->shipping_method_id,
            'tracking_number' => $carrierData['tracking_number'],
            'carrier' => $carrierData['carrier'],
            'tracking_url' => $carrierData['tracking_url'],
            'status' => isset($data['status'])
                ? ShipmentStatus::from($data['status'])
                : ShipmentStatus::Pending,
            'notes' => $data['notes'] ?? null,
        ])->load(['order', 'shippingMethod']);
    }

    public function show(Shipment $shipment): Shipment
    {
        return $shipment->load(['order', 'shippingMethod']);
    }

    /**
     * @throws ValidationException
     */
    public function transition(Shipment $shipment, ShipmentStatus $to): Shipment
    {
        return DB::transaction(function () use ($shipment, $to): Shipment {
            /** @var Shipment $locked */
            $locked = Shipment::query()->whereKey($shipment->getKey())->lockForUpdate()->firstOrFail();

            $from = $locked->status;

            if ($from === $to) {
                return $locked->load(['order', 'shippingMethod']);
            }

            $locked->status = $to;

            if (in_array($to, [ShipmentStatus::Shipped, ShipmentStatus::InTransit, ShipmentStatus::OutForDelivery], true)
                && $locked->shipped_at === null) {
                $locked->shipped_at = now();
            }

            if ($to === ShipmentStatus::Delivered && $locked->delivered_at === null) {
                $locked->delivered_at = now();
            }

            $locked->save();

            if ($to === ShipmentStatus::Shipped) {
                event(new ShipmentShipped($locked));
            }

            if ($to === ShipmentStatus::Delivered) {
                event(new ShipmentDelivered($locked));
            }

            return $locked->fresh(['order', 'shippingMethod']) ?? $locked;
        });
    }

    /**
     * @return Collection<int, Shipment>
     */
    public function forCustomerOrder(Order $order): Collection
    {
        return $order->shipments()->with('shippingMethod')->latest('id')->get();
    }

    /**
     * @param  array{per_page?: int|null}  $params
     */
    protected function perPage(array $params): int
    {
        return max(1, min((int) ($params['per_page'] ?? 15), 100));
    }
}
