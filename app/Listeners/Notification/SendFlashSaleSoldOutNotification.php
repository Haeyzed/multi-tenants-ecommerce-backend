<?php

declare(strict_types=1);

namespace App\Listeners\Notification;

use App\Enums\Notification\NotificationChannel;
use App\Events\FlashSaleItemSoldOut;
use App\Models\Tenant\User;
use App\Services\Notification\NotificationService;
use Illuminate\Support\Facades\Schema;

class SendFlashSaleSoldOutNotification
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(FlashSaleItemSoldOut $event): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('users')) {
            return;
        }

        $item = $event->flashSaleItem->loadMissing('flashSale');
        $channels = [
            NotificationChannel::Email->value,
            NotificationChannel::Database->value,
        ];

        User::permission('flash_sales.view')->each(function (User $user) use ($item, $channels): void {
            $this->notifications->send(
                $user,
                'flash_sale.sold_out',
                [
                    'user_name' => trim($user->first_name.' '.$user->last_name) ?: $user->email,
                    'flash_sale_name' => $item->flashSale?->name ?? '',
                    'flash_sale_id' => (string) $item->flash_sale_id,
                    'product_id' => (string) $item->product_id,
                ],
                $channels,
            );
        });
    }
}
