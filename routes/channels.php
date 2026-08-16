<?php

declare(strict_types=1);

use App\Models\Landlord\Tenant;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Delivery;
use App\Models\Tenant\Driver;
use App\Models\Tenant\Order;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
*/

Broadcast::channel('tenant.{id}', function ($user, string $id): bool {
    if (! $user instanceof User) {
        return false;
    }

    $tenant = tenant();

    return $tenant instanceof Tenant && (string) $tenant->getTenantKey() === (string) $id;
});

Broadcast::channel('order.{id}', function ($user, int $id): bool {
    $order = Order::query()->find($id);

    if ($order === null) {
        return false;
    }

    if ($user instanceof User) {
        return true;
    }

    if ($user instanceof Customer) {
        return $order->customer_id === $user->id;
    }

    return false;
});

Broadcast::channel('delivery.{id}', function ($user, int $id): bool {
    $delivery = Delivery::query()->with('order')->find($id);

    if ($delivery === null) {
        return false;
    }

    if ($user instanceof User) {
        return true;
    }

    if ($user instanceof Driver) {
        return $delivery->driver_id === $user->id;
    }

    if ($user instanceof Customer) {
        return $delivery->order?->customer_id === $user->id;
    }

    return false;
});

Broadcast::channel('driver.{id}', function ($user, int $id): bool {
    if ($user instanceof Driver) {
        return (int) $user->id === $id;
    }

    return $user instanceof User;
});

Broadcast::channel('customer.{id}', function ($user, int $id): bool {
    if ($user instanceof Customer) {
        return (int) $user->id === $id;
    }

    return $user instanceof User;
});
