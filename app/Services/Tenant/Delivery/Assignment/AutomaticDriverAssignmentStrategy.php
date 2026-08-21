<?php

declare(strict_types=1);

namespace App\Services\Tenant\Delivery\Assignment;

use App\Contracts\Delivery\DriverAssignmentStrategyInterface;
use App\Enums\Tenant\Delivery\DeliveryStatus;
use App\Enums\Tenant\Driver\DriverAvailability;
use App\Enums\Tenant\Driver\DriverStatus;
use App\Models\Tenant\Delivery;
use App\Models\Tenant\Driver;
use App\Models\Tenant\DriverLocation;
use App\Services\Tenant\Commerce\CommerceSettingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Deterministic automatic driver selection (lowest id first).
 */
class AutomaticDriverAssignmentStrategy implements DriverAssignmentStrategyInterface
{
    /**
     * Active delivery statuses that block a driver from receiving another assignment.
     *
     * @var list<DeliveryStatus>
     */
    private const array CONFLICTING_STATUSES = [
        DeliveryStatus::Assigned,
        DeliveryStatus::Accepted,
        DeliveryStatus::PickedUp,
        DeliveryStatus::OutForDelivery,
    ];

    /**
     * Create a new class instance.
     *
     * @param  CommerceSettingService  $commerceSettings
     */
    public function __construct(private readonly CommerceSettingService $commerceSettings) {}

    /**
     * Assign.
     *
     * @param  Delivery  $delivery
     * @return ?Driver
     */
    public function assign(Delivery $delivery): ?Driver
    {
        $delivery->loadMissing('order');

        $coordinates = $this->deliveryCoordinates($delivery);
        $radiusKm = $this->commerceSettings->deliveryAssignmentRadiusKm();

        $query = Driver::query()
            ->where('status', DriverStatus::Active)
            ->where('availability', DriverAvailability::Available)
            ->whereDoesntHave('deliveries', function (Builder $query): void {
                $query->whereIn('status', array_map(
                    static fn (DeliveryStatus $status): string => $status->value,
                    self::CONFLICTING_STATUSES,
                ));
            })
            ->orderBy('id');

        /** @var Collection<int, Driver> $candidates */
        $candidates = $query->get();

        foreach ($candidates as $driver) {
            if ($coordinates === null || $radiusKm <= 0) {
                return $driver;
            }

            $lastLocation = $this->lastLocation($driver);
            if ($lastLocation === null) {
                return $driver;
            }

            $distance = $this->distanceKm(
                $coordinates[0],
                $coordinates[1],
                (float) $lastLocation->latitude,
                (float) $lastLocation->longitude,
            );

            if ($distance <= $radiusKm) {
                return $driver;
            }
        }

        return null;
    }

    /**
     * Delivery coordinates.
     *
     * @param  Delivery  $delivery
     * @return array{0: float, 1: float}|null
     */
    protected function deliveryCoordinates(Delivery $delivery): ?array
    {
        $lat = $delivery->getAttribute('latitude');
        $lng = $delivery->getAttribute('longitude');

        if ($this->isValidCoordinatePair($lat, $lng)) {
            return [(float) $lat, (float) $lng];
        }

        /** @var array<string, mixed> $snapshot */
        $snapshot = $delivery->order?->shipping_address_snapshot ?? [];

        $lat = $snapshot['latitude'] ?? $snapshot['lat'] ?? null;
        $lng = $snapshot['longitude'] ?? $snapshot['lng'] ?? null;

        if ($this->isValidCoordinatePair($lat, $lng)) {
            return [(float) $lat, (float) $lng];
        }

        return null;
    }

    /**
     * Last location.
     *
     * @param  Driver  $driver
     * @return ?DriverLocation
     */
    protected function lastLocation(Driver $driver): ?DriverLocation
    {
        return DriverLocation::query()
            ->where('driver_id', $driver->id)
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Is valid coordinate pair.
     *
     * @param  mixed  $lat
     * @param  mixed  $lng
     * @return bool
     */
    protected function isValidCoordinatePair(mixed $lat, mixed $lng): bool
    {
        return is_numeric($lat) && is_numeric($lng);
    }

    /**
     * Distance km.
     *
     * @param  float  $lat1
     * @param  float  $lon1
     * @param  float  $lat2
     * @param  float  $lon2
     * @return float
     */
    protected function distanceKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadiusKm = 6371.0;
        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lon2 - $lon1);

        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($lonDelta / 2) ** 2;

        return 2 * $earthRadiusKm * asin(min(1, sqrt($a)));
    }
}
