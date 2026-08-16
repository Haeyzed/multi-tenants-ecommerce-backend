<?php

declare(strict_types=1);

namespace App\Listeners\Notification;

use App\Enums\Notification\NotificationChannel;
use App\Events\FlashSaleStarted;
use App\Models\Tenant\User;
use App\Services\Notification\NotificationService;
use Illuminate\Support\Facades\Schema;

class SendFlashSaleStartedNotification
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(FlashSaleStarted $event): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('users')) {
            return;
        }

        $flashSale = $event->flashSale;
        $channels = [
            NotificationChannel::Email->value,
            NotificationChannel::Database->value,
        ];

        User::permission('flash_sales.view')->each(function (User $user) use ($flashSale, $channels): void {
            $this->notifications->send(
                $user,
                'flash_sale.started',
                [
                    'user_name' => trim($user->first_name.' '.$user->last_name) ?: $user->email,
                    'flash_sale_name' => $flashSale->name,
                    'flash_sale_id' => (string) $flashSale->id,
                    'ends_at' => $flashSale->ends_at?->toDateTimeString() ?? '',
                ],
                $channels,
            );
        });
    }
}
