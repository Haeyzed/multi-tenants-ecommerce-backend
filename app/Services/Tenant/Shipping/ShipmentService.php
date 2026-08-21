<?php

declare(strict_types=1);

namespace App\Services\Tenant\Shipping;

use App\DTO\Shipping\ShipmentCancellationResult;
use App\DTO\Shipping\ShipmentLabelResult;
use App\DTO\Shipping\ShipmentTrackingResult;
use App\Enums\Tenant\Commerce\ShipmentStatus;
use App\Events\ShipmentDelivered;
use App\Events\ShipmentShipped;
use App\Models\Tenant\Order;
use App\Models\Tenant\Shipment;
use App\Models\Tenant\ShippingMethod;
use App\Services\Shipping\ShippingCarrierManager;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Create and transition shipments for orders.
 */
class ShipmentService
{
    /**
     * Create a new class instance.
     *
     * @param  ShippingCarrierManager  $carriers
     */
    public function __construct(
        private readonly ShippingCarrierManager $carriers,
    ) {}

    /**
     * Retrieve a paginated list of resources.
     *
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
     * shipping_method_id?: int|null, tracking_number?: string|null, carrier?: string|null, tracking_url?: string|null, notes?: string|null, status?: string|null }  $data
     *
     * @param  Order  $order
     * @param  array{
     *     shipping_method_id?: int|null,
     *     tracking_number?: string|null,
     *     carrier?: string|null,
     *     tracking_url?: string|null,
     *     notes?: string|null,
     *     status?: string|null
     * }  $data
     * @return Shipment
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

    /**
     * Retrieve a single resource.
     *
     * @param  Shipment  $shipment
     * @return Shipment
     */
    public function show(Shipment $shipment): Shipment
    {
        return $shipment->load(['order', 'shippingMethod']);
    }

    /**
     * Transition.
     *
     * @param  Shipment  $shipment
     * @param  ShipmentStatus  $to
     * @return Shipment
     *
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
     * For customer order.
     *
     * @param  Order  $order
     * @param  array{per_page?: int|null}  $params
     * @return LengthAwarePaginator<int, Shipment>
     */
    public function forCustomerOrder(Order $order, array $params = []): LengthAwarePaginator
    {
        return $order->shipments()
            ->with('shippingMethod')
            ->latest('id')
            ->paginate($this->perPage($params));
    }

    /**
     * Track a shipment via its configured carrier (or the default driver).
     *
     * @param  Shipment  $shipment
     * @return ShipmentTrackingResult
     */
    public function trackViaCarrier(Shipment $shipment): ShipmentTrackingResult
    {
        $driver = $shipment->carrier !== null && $shipment->carrier !== ''
            ? $shipment->carrier
            : null;

        return $this->carriers->driver($driver)->trackShipment((string) $shipment->tracking_number);
    }

    /**
     * Cancel a shipment via its carrier, then optionally mark it Cancelled locally.
     *
     * @param  Shipment  $shipment
     * @return ShipmentCancellationResult
     */
    public function cancelViaCarrier(Shipment $shipment): ShipmentCancellationResult
    {
        $driver = $shipment->carrier !== null && $shipment->carrier !== ''
            ? $shipment->carrier
            : null;

        $result = $this->carriers->cancelShipment(
            (string) $shipment->tracking_number,
            [],
            $driver,
        );

        if ($result->successful) {
            $this->transition($shipment, ShipmentStatus::Cancelled);
        }

        return $result;
    }

    /**
     * Fetch a shipping label via the shipment's carrier (or the default driver).
     *
     * @param  Shipment  $shipment
     * @return ShipmentLabelResult
     */
    public function labelViaCarrier(Shipment $shipment): ShipmentLabelResult
    {
        $driver = $shipment->carrier !== null && $shipment->carrier !== ''
            ? $shipment->carrier
            : null;

        return $this->carriers->getLabel((string) $shipment->tracking_number, [], $driver);
    }

    /**
     * Apply a normalized carrier status string to a shipment looked up by tracking number.
     *
     * @param  string  $trackingNumber
     * @param  string  $status
     * @return ?Shipment
     */
    public function applyCarrierStatus(string $trackingNumber, string $status): ?Shipment
    {
        if ($trackingNumber === '') {
            return null;
        }

        $shipment = Shipment::query()
            ->where('tracking_number', $trackingNumber)
            ->first();

        if ($shipment === null) {
            return null;
        }

        $mapped = $this->mapCarrierStatus($status);

        if ($mapped === null) {
            return null;
        }

        return $this->transition($shipment, $mapped);
    }

    /**
     * Map a carrier-normalized status string to {@see ShipmentStatus}.
     *
     * @param  string  $status
     * @return ?ShipmentStatus
     */
    protected function mapCarrierStatus(string $status): ?ShipmentStatus
    {
        $normalized = Str::of($status)
            ->trim()
            ->lower()
            ->replace(['-', ' '], '_')
            ->toString();

        return match ($normalized) {
            'pending' => ShipmentStatus::Pending,
            'processing' => ShipmentStatus::Processing,
            'shipped' => ShipmentStatus::Shipped,
            'in_transit' => ShipmentStatus::InTransit,
            'out_for_delivery' => ShipmentStatus::OutForDelivery,
            'delivered' => ShipmentStatus::Delivered,
            'failed' => ShipmentStatus::Failed,
            'cancelled', 'canceled' => ShipmentStatus::Cancelled,
            default => null,
        };
    }

    /**
     * Resolve the page size for paginated listings.
     *
     * @param  array{per_page?: int|null}  $params
     * @return int
     */
    protected function perPage(array $params): int
    {
        return max(1, min((int) ($params['per_page'] ?? 15), 100));
    }
}
