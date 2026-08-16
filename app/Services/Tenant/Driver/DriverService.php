<?php

declare(strict_types=1);

namespace App\Services\Tenant\Driver;

use App\Enums\Tenant\Delivery\DeliveryStatus;
use App\Enums\Tenant\Driver\DriverAvailability;
use App\Enums\Tenant\Driver\DriverStatus;
use App\Models\Landlord\Tenant;
use App\Models\Tenant\Delivery;
use App\Models\Tenant\Driver;
use App\Services\Landlord\Feature\UsageLimiter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Staff and self-service driver management.
 */
class DriverService
{
    public function __construct(private readonly UsageLimiter $usageLimiter) {}

    /**
     * Paginate drivers with search, filters, and sorts.
     *
     * @param  array{
     *     search?: string|null,
     *     status?: string|null,
     *     availability?: string|null,
     *     sort?: string|null,
     *     per_page?: int|null
     * }  $params
     * @return LengthAwarePaginator<int, Driver>
     */
    public function list(array $params = []): LengthAwarePaginator
    {
        return Driver::query()
            ->withTrashed()
            ->filter($params)
            ->applySort($params['sort'] ?? null)
            ->paginate($this->perPage($params));
    }

    /**
     * Create a driver account (staff only — no public registration).
     *
     * @param  array{
     *     first_name: string,
     *     last_name: string,
     *     email: string,
     *     phone?: string|null,
     *     password: string,
     *     status?: DriverStatus|string|null,
     *     availability?: DriverAvailability|string|null
     * }  $data
     */
    public function store(array $data): Driver
    {
        $tenant = tenant();
        if ($tenant instanceof Tenant && $tenant->activeSubscription() !== null) {
            $this->usageLimiter->assertCanCreate('drivers', $tenant);
        }

        return Driver::query()->create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
            'status' => $data['status'] ?? DriverStatus::Active,
            'availability' => $data['availability'] ?? DriverAvailability::Unavailable,
            'email_verified_at' => now(),
        ]);
    }

    /**
     * Retrieve a driver.
     */
    public function show(Driver $driver): Driver
    {
        return $driver;
    }

    /**
     * Update a driver (admin — password optional).
     *
     * @param  array{
     *     first_name?: string,
     *     last_name?: string,
     *     email?: string,
     *     phone?: string|null,
     *     password?: string|null,
     *     status?: DriverStatus|string,
     *     availability?: DriverAvailability|string
     * }  $data
     */
    public function update(Driver $driver, array $data): Driver
    {
        if (array_key_exists('password', $data) && ($data['password'] === null || $data['password'] === '')) {
            unset($data['password']);
        }

        $driver->fill($data);
        $driver->save();

        if (isset($data['status']) && DriverStatus::from(
            $data['status'] instanceof DriverStatus ? $data['status']->value : (string) $data['status']
        ) !== DriverStatus::Active) {
            $driver->tokens()->delete();
        }

        return $driver->fresh() ?? $driver;
    }

    /**
     * Soft-delete a driver and revoke tokens.
     */
    public function destroy(Driver $driver): void
    {
        $driver->tokens()->delete();
        $driver->delete();
    }

    /**
     * Paginate delivery history for a driver.
     *
     * @param  array{status?: string|null, per_page?: int|null}  $params
     * @return LengthAwarePaginator<int, Delivery>
     */
    public function deliveryHistory(Driver $driver, array $params = []): LengthAwarePaginator
    {
        $query = Delivery::query()
            ->with(['order', 'shipment'])
            ->where('driver_id', $driver->id)
            ->latest('id');

        if (! empty($params['status'])) {
            $query->where('status', $params['status']);
        }

        return $query->paginate($this->perPage($params));
    }

    /**
     * Aggregate delivery counts by status for a driver.
     *
     * @return array{
     *     total: int,
     *     by_status: array<string, int>
     * }
     */
    public function stats(Driver $driver): array
    {
        $counts = Delivery::query()
            ->where('driver_id', $driver->id)
            ->toBase()
            ->select('status', DB::raw('count(*) as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $byStatus = [];
        $total = 0;

        foreach (DeliveryStatus::cases() as $status) {
            $count = (int) ($counts[$status->value] ?? 0);
            $byStatus[$status->value] = $count;
            $total += $count;
        }

        return [
            'total' => $total,
            'by_status' => $byStatus,
        ];
    }

    /**
     * Update the authenticated driver's profile.
     *
     * @param  array{first_name?: string, last_name?: string, email?: string, phone?: string|null, availability?: DriverAvailability|string}  $data
     */
    public function updateProfile(Driver $driver, array $data): Driver
    {
        $driver->fill($data);
        $driver->save();

        return $driver->fresh() ?? $driver;
    }

    /**
     * @param  array{per_page?: int|null}  $params
     */
    protected function perPage(array $params): int
    {
        return max(1, min((int) ($params['per_page'] ?? 15), 100));
    }
}
