<?php

declare(strict_types=1);

namespace App\Services\Notification;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Authenticated user inbox over Laravel database notifications.
 */
class NotificationInboxService
{
    /**
     * @param  array{per_page?: int|null}  $params
     * @return LengthAwarePaginator<int, DatabaseNotification>
     */
    public function list(Model $user, array $params = []): LengthAwarePaginator
    {
        /** @var Model&object{notifications: MorphMany} $user */
        return $user->notifications()->paginate((int) ($params['per_page'] ?? 15));
    }

    /**
     * @param  array{per_page?: int|null}  $params
     * @return LengthAwarePaginator<int, DatabaseNotification>
     */
    public function unread(Model $user, array $params = []): LengthAwarePaginator
    {
        /** @var Model&object{unreadNotifications: MorphMany} $user */
        return $user->unreadNotifications()->paginate((int) ($params['per_page'] ?? 15));
    }

    public function unreadCount(Model $user): int
    {
        /** @var Model&object{unreadNotifications: MorphMany} $user */
        return $user->unreadNotifications()->count();
    }

    public function findOwned(Model $user, string $notificationId): DatabaseNotification
    {
        /** @var Model&object{notifications: MorphMany} $user */
        /** @var DatabaseNotification $notification */
        $notification = $user->notifications()->whereKey($notificationId)->firstOrFail();

        return $notification;
    }

    public function markAsRead(Model $user, string $notificationId): DatabaseNotification
    {
        $notification = $this->findOwned($user, $notificationId);
        $notification->markAsRead();

        return $notification->refresh();
    }

    public function markAsUnread(Model $user, string $notificationId): DatabaseNotification
    {
        $notification = $this->findOwned($user, $notificationId);
        $notification->forceFill(['read_at' => null])->save();

        return $notification->refresh();
    }

    public function markAllAsRead(Model $user): int
    {
        /** @var Model&object{unreadNotifications: MorphMany} $user */
        $count = $user->unreadNotifications()->count();
        $user->unreadNotifications()->update(['read_at' => now()]);

        return $count;
    }

    public function delete(Model $user, string $notificationId): void
    {
        $this->findOwned($user, $notificationId)->delete();
    }
}
