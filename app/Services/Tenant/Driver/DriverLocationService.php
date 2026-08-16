<?php

declare(strict_types=1);

namespace App\Services\Tenant\Driver;

use App\Events\DriverLocationUpdated;
use App\Models\Tenant\Delivery;
use App\Models\Tenant\Driver;
use App\Models\Tenant\DriverLocation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

/**
 * Persist and broadcast driver GPS updates for active deliveries.
 */
class DriverLocationService
{
    /**
     * Record a location fix for a driver on an active delivery.
     *
     * Persistence is throttled per delivery. Broadcasts are throttled separately.
     *
     * @param  array{
     *     latitude: float|string,
     *     longitude: float|string,
     *     accuracy?: float|string|null,
     *     heading?: float|string|null,
     *     speed?: float|string|null,
     *     recorded_at?: string|null
     * }  $data
     *
     * @throws ValidationException
     */
    public function record(Driver $driver, Delivery $delivery, array $data): ?DriverLocation
    {
        if ($delivery->driver_id !== $driver->id) {
            throw ValidationException::withMessages([
                'delivery_id' => ['Delivery is not assigned to this driver.'],
            ]);
        }

        if (! $delivery->isActiveForDriver()) {
            throw ValidationException::withMessages([
                'delivery_id' => ['Location updates are only allowed for active deliveries.'],
            ]);
        }

        $minPersistSeconds = max(1, (int) config('delivery.location.min_persist_seconds', 5));
        $persistKey = 'driver_location:persist:'.$delivery->id;

        $shouldPersist = Cache::add($persistKey, true, now()->addSeconds($minPersistSeconds));

        $location = null;

        if ($shouldPersist) {
            $location = DriverLocation::query()->create([
                'driver_id' => $driver->id,
                'delivery_id' => $delivery->id,
                'latitude' => $data['latitude'],
                'longitude' => $data['longitude'],
                'accuracy' => $data['accuracy'] ?? null,
                'heading' => $data['heading'] ?? null,
                'speed' => $data['speed'] ?? null,
                'recorded_at' => $data['recorded_at'] ?? now(),
            ]);
        }

        $broadcastSeconds = max(1, (int) config('delivery.location.broadcast_throttle_seconds', 4));
        $broadcastKey = 'driver_location:broadcast:'.$delivery->id;

        if (Cache::lock($broadcastKey, $broadcastSeconds)->get()) {
            event(new DriverLocationUpdated(
                $driver,
                $delivery,
                (string) $data['latitude'],
                (string) $data['longitude'],
                isset($data['accuracy']) ? (string) $data['accuracy'] : null,
                isset($data['heading']) ? (string) $data['heading'] : null,
                isset($data['speed']) ? (string) $data['speed'] : null,
                $location,
            ));
        }

        return $location;
    }
}
