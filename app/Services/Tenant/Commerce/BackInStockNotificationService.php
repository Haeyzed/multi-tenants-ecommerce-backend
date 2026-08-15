<?php

declare(strict_types=1);

namespace App\Services\Tenant\Commerce;

use App\Enums\Notification\NotificationChannel;
use App\Enums\Tenant\Catalog\ProductAvailability;
use App\Models\Tenant\Inventory;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductStockSubscription;
use App\Models\Tenant\ProductVariant;
use App\Services\Notification\NotificationService;
use App\Services\Tenant\Product\ProductAvailabilityService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Notifies customers when subscribed products are back in stock.
 */
class BackInStockNotificationService
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly ProductAvailabilityService $availability,
    ) {}

    /**
     * Notify subscribers when inventory crosses from unavailable to available.
     */
    public function handleInventoryChange(Inventory $inventory, int $availableBefore, int $availableAfter): void
    {
        if (! Schema::hasTable('product_stock_subscriptions')) {
            return;
        }

        $inventory->loadMissing('inventoryable');

        if ($availableBefore <= 0 && $availableAfter > 0) {
            $this->notifySubscribers($inventory->inventoryable);
        }

        if ($availableAfter <= 0) {
            $this->resetSubscriptions($inventory->inventoryable);
        }
    }

    /**
     * Subscribe a customer to back-in-stock alerts for a product or variant.
     */
    public function subscribe(
        int $customerId,
        int $productId,
        ?int $productVariantId = null,
    ): ProductStockSubscription {
        return ProductStockSubscription::query()->updateOrCreate(
            [
                'customer_id' => $customerId,
                'product_id' => $productId,
                'product_variant_id' => $productVariantId,
            ],
            [
                'notified_at' => null,
            ],
        );
    }

    /**
     * @param  Product|ProductVariant|null  $inventoryable
     */
    protected function notifySubscribers(?Model $inventoryable): void
    {
        if ($inventoryable === null) {
            return;
        }

        [$productId, $variantId] = $this->resolveProductAndVariant($inventoryable);

        $availability = $inventoryable instanceof ProductVariant
            ? $this->availability->forVariant($inventoryable)
            : $this->availability->forProduct($inventoryable);

        if ($availability === ProductAvailability::Unavailable) {
            return;
        }

        $product = $inventoryable instanceof Product
            ? $inventoryable
            : $inventoryable->product;

        ProductStockSubscription::query()
            ->with('customer')
            ->where('product_id', $productId)
            ->where(function ($query) use ($variantId): void {
                if ($variantId === null) {
                    $query->whereNull('product_variant_id');
                } else {
                    $query->where(function ($query) use ($variantId): void {
                        $query->whereNull('product_variant_id')
                            ->orWhere('product_variant_id', $variantId);
                    });
                }
            })
            ->whereNull('notified_at')
            ->chunkById(100, function ($subscriptions) use ($product): void {
                foreach ($subscriptions as $subscription) {
                    /** @var ProductStockSubscription $subscription */
                    $customer = $subscription->customer;

                    if ($customer === null) {
                        continue;
                    }

                    $this->notifications->send(
                        $customer,
                        'wishlist.back_in_stock',
                        [
                            'user_name' => $customer->full_name,
                            'product_name' => $product?->name ?? '',
                            'product_id' => $subscription->product_id,
                        ],
                        [
                            NotificationChannel::Email->value,
                            NotificationChannel::Database->value,
                        ],
                    );

                    $subscription->notified_at = now();
                    $subscription->save();
                }
            });
    }

    /**
     * @param  Product|ProductVariant|null  $inventoryable
     */
    protected function resetSubscriptions(?Model $inventoryable): void
    {
        if ($inventoryable === null) {
            return;
        }

        [$productId, $variantId] = $this->resolveProductAndVariant($inventoryable);

        ProductStockSubscription::query()
            ->where('product_id', $productId)
            ->when(
                $variantId === null,
                fn ($query) => $query->whereNull('product_variant_id'),
                fn ($query) => $query->where('product_variant_id', $variantId),
            )
            ->update(['notified_at' => null]);
    }

    /**
     * @param  Product|ProductVariant  $inventoryable
     * @return array{0: int, 1: int|null}
     */
    protected function resolveProductAndVariant(Model $inventoryable): array
    {
        if ($inventoryable instanceof ProductVariant) {
            return [(int) $inventoryable->product_id, (int) $inventoryable->id];
        }

        return [(int) $inventoryable->getKey(), null];
    }
}
