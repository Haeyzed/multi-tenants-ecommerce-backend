<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\Notification\NotificationChannel;
use App\Enums\Tenant\Commerce\CartStatus;
use App\Models\Tenant\Cart;
use App\Services\Notification\NotificationService;
use App\Services\Tenant\Commerce\CommerceAnalyticsService;
use App\Services\Tenant\Commerce\CommerceSettingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Stancl\Tenancy\Contracts\TenantWithDatabase;

/**
 * Mark stale active carts as abandoned and notify customers once.
 */
class MarkAbandonedCartsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public ?string $tenantId = null,
    ) {}

    public function handle(
        CommerceSettingService $commerceSettings,
        NotificationService $notifications,
        CommerceAnalyticsService $analytics,
    ): void {
        if ($this->tenantId !== null) {
            /** @var TenantWithDatabase|null $tenant */
            $tenant = tenancy()->find($this->tenantId);
            if ($tenant !== null) {
                tenancy()->initialize($tenant);
            }
        }

        $hours = $commerceSettings->cartAbandonAfterHours();
        $cutoff = now()->subHours($hours);

        Cart::query()
            ->with(['customer', 'items'])
            ->where('status', CartStatus::Active)
            ->where('updated_at', '<=', $cutoff)
            ->whereNull('abandoned_at')
            ->whereHas('items')
            ->chunkById(100, function ($carts) use ($notifications, $analytics): void {
                foreach ($carts as $cart) {
                    /** @var Cart $cart */
                    $cart->status = CartStatus::Abandoned;
                    $cart->abandoned_at = now();
                    $cart->save();

                    if ($cart->abandoned_notified_at !== null || $cart->customer === null) {
                        continue;
                    }

                    $notifications->sendNow(
                        $cart->customer,
                        'cart.abandoned',
                        [
                            'user_name' => $cart->customer->full_name,
                            'cart_id' => $cart->id,
                            'item_count' => $cart->items->count(),
                        ],
                        [
                            NotificationChannel::Email->value,
                            NotificationChannel::Database->value,
                        ],
                    );

                    $cart->abandoned_notified_at = now();
                    $cart->save();

                    $analytics->record('cart.abandoned', $cart, $cart->customer, [
                        'cart_id' => $cart->id,
                        'item_count' => $cart->items->count(),
                    ]);
                }
            });
    }
}
