<?php

declare(strict_types=1);

namespace App\Services\Tenant\Delivery;

use App\Enums\Tenant\Delivery\DeliveryStatus;
use App\Enums\Tenant\Driver\DriverAvailability;
use App\Events\DeliveryAccepted;
use App\Events\DeliveryAssigned;
use App\Events\DeliveryCompleted;
use App\Events\DeliveryStarted;
use App\Models\Tenant\Delivery;
use App\Models\Tenant\Driver;
use App\Models\Tenant\Order;
use App\Models\Tenant\Shipment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Create and transition deliveries with a strict status machine.
 */
class DeliveryService
{
    /**
     * Allowed transitions keyed by current status.
     *
     * @var array<string, list<DeliveryStatus>>
     */
    private const array TRANSITIONS = [
        'pending' => [DeliveryStatus::Assigned, DeliveryStatus::Cancelled],
        'assigned' => [DeliveryStatus::Accepted, DeliveryStatus::Cancelled],
        'accepted' => [DeliveryStatus::PickedUp, DeliveryStatus::Cancelled],
        'picked_up' => [DeliveryStatus::OutForDelivery],
        'out_for_delivery' => [DeliveryStatus::Delivered, DeliveryStatus::Failed, DeliveryStatus::Cancelled],
        'delivered' => [],
        'failed' => [],
        'cancelled' => [],
    ];

    /**
     * @param  array{
     *     order_id?: int|null,
     *     driver_id?: int|null,
     *     status?: string|null,
     *     per_page?: int|null
     * }  $params
     * @return LengthAwarePaginator<int, Delivery>
     */
    public function list(array $params = []): LengthAwarePaginator
    {
        $query = Delivery::query()
            ->with(['order', 'shipment', 'driver'])
            ->latest('id');

        if (! empty($params['order_id'])) {
            $query->where('order_id', (int) $params['order_id']);
        }

        if (! empty($params['driver_id'])) {
            $query->where('driver_id', (int) $params['driver_id']);
        }

        if (! empty($params['status'])) {
            $query->where('status', $params['status']);
        }

        return $query->paginate($this->perPage($params));
    }

    /**
     * Create a pending delivery for an order (optionally linked to a shipment).
     *
     * @param  array{notes?: string|null}  $data
     */
    public function createForOrder(Order $order, ?Shipment $shipment = null, array $data = []): Delivery
    {
        if ($shipment !== null && $shipment->order_id !== $order->id) {
            throw ValidationException::withMessages([
                'shipment_id' => ['Shipment does not belong to this order.'],
            ]);
        }

        return Delivery::query()->create([
            'order_id' => $order->id,
            'shipment_id' => $shipment?->id,
            'status' => DeliveryStatus::Pending,
            'notes' => $data['notes'] ?? null,
        ])->load(['order', 'shipment', 'driver']);
    }

    /**
     * Create a pending delivery for a shipment.
     *
     * @param  array{notes?: string|null}  $data
     */
    public function createForShipment(Shipment $shipment, array $data = []): Delivery
    {
        $order = $shipment->order ?? Order::query()->findOrFail($shipment->order_id);

        return $this->createForOrder($order, $shipment, $data);
    }

    /**
     * Retrieve a delivery with relations.
     */
    public function show(Delivery $delivery): Delivery
    {
        return $delivery->load(['order', 'shipment', 'driver']);
    }

    /**
     * Assign a driver to a pending (or re-assignable) delivery.
     *
     * @throws ValidationException
     */
    public function assign(Delivery $delivery, Driver $driver): Delivery
    {
        return DB::transaction(function () use ($delivery, $driver): Delivery {
            /** @var Delivery $locked */
            $locked = Delivery::query()->whereKey($delivery->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status === DeliveryStatus::Pending) {
                $this->assertCanTransition($locked, DeliveryStatus::Assigned);
            } elseif ($locked->status === DeliveryStatus::Assigned) {
                // Re-assign while still awaiting acceptance.
            } else {
                throw ValidationException::withMessages([
                    'status' => ['Delivery can only be assigned while pending or already assigned.'],
                ]);
            }

            if (! $driver->isLoginAllowed()) {
                throw ValidationException::withMessages([
                    'driver_id' => ['Driver is not available for assignment.'],
                ]);
            }

            $locked->forceFill([
                'driver_id' => $driver->id,
                'status' => DeliveryStatus::Assigned,
                'assigned_at' => now(),
                'accepted_at' => null,
            ])->save();

            $fresh = $locked->fresh(['order', 'shipment', 'driver']) ?? $locked;

            event(new DeliveryAssigned($fresh));

            return $fresh;
        });
    }

    /**
     * Driver accepts an assigned delivery.
     *
     * @throws ValidationException
     */
    public function accept(Delivery $delivery, Driver $driver): Delivery
    {
        return DB::transaction(function () use ($delivery, $driver): Delivery {
            /** @var Delivery $locked */
            $locked = Delivery::query()->whereKey($delivery->getKey())->lockForUpdate()->firstOrFail();

            $this->assertDriverOwns($locked, $driver);
            $this->assertCanTransition($locked, DeliveryStatus::Accepted);

            $locked->forceFill([
                'status' => DeliveryStatus::Accepted,
                'accepted_at' => now(),
            ])->save();

            $driver->forceFill([
                'availability' => DriverAvailability::OnDelivery,
            ])->save();

            $fresh = $locked->fresh(['order', 'shipment', 'driver']) ?? $locked;

            event(new DeliveryAccepted($fresh));

            return $fresh;
        });
    }

    /**
     * Driver rejects an assigned delivery (returns to pending).
     *
     * @throws ValidationException
     */
    public function reject(Delivery $delivery, Driver $driver): Delivery
    {
        return DB::transaction(function () use ($delivery, $driver): Delivery {
            /** @var Delivery $locked */
            $locked = Delivery::query()->whereKey($delivery->getKey())->lockForUpdate()->firstOrFail();

            $this->assertDriverOwns($locked, $driver);

            if ($locked->status !== DeliveryStatus::Assigned) {
                throw ValidationException::withMessages([
                    'status' => ['Only assigned deliveries can be rejected.'],
                ]);
            }

            $locked->forceFill([
                'driver_id' => null,
                'status' => DeliveryStatus::Pending,
                'assigned_at' => null,
                'accepted_at' => null,
            ])->save();

            return $locked->fresh(['order', 'shipment', 'driver']) ?? $locked;
        });
    }

    /**
     * Mark delivery as picked up.
     *
     * @throws ValidationException
     */
    public function markPickedUp(Delivery $delivery, ?Driver $driver = null): Delivery
    {
        return $this->advance($delivery, DeliveryStatus::PickedUp, $driver, function (Delivery $locked): void {
            $locked->picked_up_at = now();
        }, broadcastStarted: true);
    }

    /**
     * Mark delivery as out for delivery.
     *
     * @throws ValidationException
     */
    public function markOutForDelivery(Delivery $delivery, ?Driver $driver = null): Delivery
    {
        return $this->advance($delivery, DeliveryStatus::OutForDelivery, $driver, function (Delivery $locked): void {
            $locked->out_for_delivery_at = now();
        }, broadcastStarted: true);
    }

    /**
     * Mark delivery as delivered.
     *
     * @throws ValidationException
     */
    public function markDelivered(Delivery $delivery, ?Driver $driver = null): Delivery
    {
        return $this->advance($delivery, DeliveryStatus::Delivered, $driver, function (Delivery $locked): void {
            $locked->delivered_at = now();
        }, completeDriver: true, broadcastCompleted: true);
    }

    /**
     * Mark delivery as failed.
     *
     * @param  array{failure_reason?: string|null}  $data
     *
     * @throws ValidationException
     */
    public function markFailed(Delivery $delivery, array $data = [], ?Driver $driver = null): Delivery
    {
        return $this->advance($delivery, DeliveryStatus::Failed, $driver, function (Delivery $locked) use ($data): void {
            $locked->failed_at = now();
            $locked->failure_reason = $data['failure_reason'] ?? null;
        }, completeDriver: true, broadcastCompleted: true);
    }

    /**
     * Cancel a delivery.
     *
     * @throws ValidationException
     */
    public function cancel(Delivery $delivery, ?Driver $driver = null): Delivery
    {
        return $this->advance($delivery, DeliveryStatus::Cancelled, $driver, function (Delivery $locked): void {
            $locked->cancelled_at = now();
        }, completeDriver: true);
    }

    /**
     * List deliveries for a specific driver.
     *
     * @param  array{status?: string|null, per_page?: int|null}  $params
     * @return LengthAwarePaginator<int, Delivery>
     */
    public function forDriver(Driver $driver, array $params = []): LengthAwarePaginator
    {
        $params['driver_id'] = $driver->id;

        return $this->list($params);
    }

    /**
     * List deliveries for a customer's order.
     *
     * @param  array{per_page?: int|null}  $params
     * @return LengthAwarePaginator<int, Delivery>
     */
    public function forCustomerOrder(Order $order, array $params = []): LengthAwarePaginator
    {
        return $order->deliveries()
            ->with(['shipment', 'driver'])
            ->latest('id')
            ->paginate($this->perPage($params));
    }

    /**
     * @param  callable(Delivery): void  $mutator
     *
     * @throws ValidationException
     */
    protected function advance(
        Delivery $delivery,
        DeliveryStatus $to,
        ?Driver $driver,
        callable $mutator,
        bool $completeDriver = false,
        bool $broadcastStarted = false,
        bool $broadcastCompleted = false,
    ): Delivery {
        return DB::transaction(function () use ($delivery, $to, $driver, $mutator, $completeDriver, $broadcastStarted, $broadcastCompleted): Delivery {
            /** @var Delivery $locked */
            $locked = Delivery::query()->whereKey($delivery->getKey())->lockForUpdate()->firstOrFail();

            if ($driver !== null) {
                $this->assertDriverOwns($locked, $driver);
            }

            $this->assertCanTransition($locked, $to);

            $locked->status = $to;
            $mutator($locked);
            $locked->save();

            if ($completeDriver && $locked->driver_id !== null) {
                $assignedDriver = Driver::query()->whereKey($locked->driver_id)->lockForUpdate()->first();
                if ($assignedDriver !== null) {
                    $assignedDriver->forceFill([
                        'availability' => DriverAvailability::Available,
                    ])->save();
                }
            }

            $fresh = $locked->fresh(['order', 'shipment', 'driver']) ?? $locked;

            if ($broadcastStarted) {
                event(new DeliveryStarted($fresh));
            }

            if ($broadcastCompleted) {
                event(new DeliveryCompleted($fresh));
            }

            return $fresh;
        });
    }

    /**
     * @throws ValidationException
     */
    protected function assertCanTransition(Delivery $delivery, DeliveryStatus $to): void
    {
        $allowed = self::TRANSITIONS[$delivery->status->value] ?? [];

        if (! in_array($to, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => [sprintf(
                    'Cannot transition delivery from %s to %s.',
                    $delivery->status->value,
                    $to->value,
                )],
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    protected function assertDriverOwns(Delivery $delivery, Driver $driver): void
    {
        if ($delivery->driver_id !== $driver->id) {
            throw ValidationException::withMessages([
                'driver' => ['This delivery is not assigned to you.'],
            ]);
        }
    }

    /**
     * @param  array{per_page?: int|null}  $params
     */
    protected function perPage(array $params): int
    {
        return max(1, min((int) ($params['per_page'] ?? 15), 100));
    }
}
