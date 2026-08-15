<?php

declare(strict_types=1);

namespace App\Services\Landlord\Feature;

use App\Models\Landlord\Tenant;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Order;
use App\Models\Tenant\Product;
use App\Models\Tenant\Seller;
use App\Models\Tenant\User;
use App\Models\Tenant\Warehouse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Enforces plan usage limits against live tenant resource counts.
 */
class UsageLimiter
{
    public function __construct(
        private readonly FeatureAccessService $features,
        private readonly FeatureGate $gate,
    ) {}

    /**
     * Assert the tenant may create another unit of the given limited resource.
     *
     * @throws ValidationException
     */
    public function assertCanCreate(string $featureSlug, ?Tenant $tenant = null, int $quantity = 1): void
    {
        $tenant ??= $this->currentTenant();

        if ($tenant === null) {
            throw ValidationException::withMessages([
                'feature' => 'Tenant context is required.',
            ]);
        }

        $this->gate->assert($featureSlug, $tenant);

        $usage = $this->currentUsage($featureSlug, $tenant);
        $limit = $this->features->limit($tenant, $featureSlug);

        if ($limit === null) {
            return;
        }

        if (($usage + $quantity) > $limit) {
            throw ValidationException::withMessages([
                'feature' => "Plan limit reached for [{$featureSlug}] ({$usage}/{$limit}).",
            ]);
        }
    }

    /**
     * Whether the tenant can create another unit of the resource.
     */
    public function canCreate(string $featureSlug, ?Tenant $tenant = null, int $quantity = 1): bool
    {
        try {
            $this->assertCanCreate($featureSlug, $tenant, $quantity);

            return true;
        } catch (ValidationException) {
            return false;
        }
    }

    /**
     * Current usage count for a feature slug.
     */
    public function currentUsage(string $featureSlug, ?Tenant $tenant = null): int
    {
        return match ($featureSlug) {
            'products' => Schema::hasTable('products') ? Product::query()->count() : 0,
            'customers' => Schema::hasTable('customers') ? Customer::query()->count() : 0,
            'orders' => $this->ordersThisMonth(),
            'inventory', 'warehouses' => Schema::hasTable('warehouses') ? Warehouse::query()->count() : 0,
            'users' => Schema::hasTable('users') ? User::query()->count() : 0,
            'sellers' => Schema::hasTable('sellers') ? Seller::query()->count() : 0,
            default => 0,
        };
    }

    /**
     * Remaining units before the plan limit (null when unlimited / unavailable).
     */
    public function remaining(string $featureSlug, ?Tenant $tenant = null): ?int
    {
        $tenant ??= $this->currentTenant();

        if ($tenant === null || ! $this->gate->allows($featureSlug, $tenant)) {
            return 0;
        }

        $limit = $this->features->limit($tenant, $featureSlug);

        if ($limit === null) {
            return null;
        }

        return max(0, $limit - $this->currentUsage($featureSlug, $tenant));
    }

    protected function ordersThisMonth(): int
    {
        if (! Schema::hasTable('orders')) {
            return 0;
        }

        $start = Carbon::now()->startOfMonth();

        return Order::query()->where('placed_at', '>=', $start)->count();
    }

    protected function currentTenant(): ?Tenant
    {
        $tenant = tenant();

        return $tenant instanceof Tenant ? $tenant : null;
    }
}
