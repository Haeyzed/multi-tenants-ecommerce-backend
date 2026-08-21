<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Notification;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Notification\IndexInboxRequest;
use App\Http\Resources\Tenant\Notification\InboxNotificationResource;
use App\Models\Tenant\User;
use App\Services\Notification\NotificationInboxService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Tenant authenticated user notification inbox.
 */
class InboxController extends Controller
{
    /**
     * Create a new class instance.
     *
     * @param  NotificationInboxService  $inbox
     */
    public function __construct(private readonly NotificationInboxService $inbox) {}

    /**
     * List resources with pagination and filters.
     *
     * @param  IndexInboxRequest  $request
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Paginated inbox notifications.',
        type: 'array{success: true, message: string, data: InboxNotificationResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}',
    )]
    public function index(IndexInboxRequest $request): JsonResponse
    {
        $notifications = $this->inbox->list($this->actor(), $request->validated());

        return $this->success(
            InboxNotificationResource::collection($notifications->items()),
            'Notifications retrieved successfully.',
            $this->paginationMeta($notifications),
        );
    }

    /**
     * Unread.
     *
     * @param  IndexInboxRequest  $request
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Paginated unread notifications.',
        type: 'array{success: true, message: string, data: InboxNotificationResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}',
    )]
    public function unread(IndexInboxRequest $request): JsonResponse
    {
        $notifications = $this->inbox->unread($this->actor(), $request->validated());

        return $this->success(
            InboxNotificationResource::collection($notifications->items()),
            'Unread notifications retrieved successfully.',
            $this->paginationMeta($notifications),
        );
    }

    /**
     * Unread count.
     *
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Unread notification count.',
        type: 'array{success: true, message: string, data: array{count: int}, meta: null, errors: null}',
    )]
    public function unreadCount(): JsonResponse
    {
        return $this->success(
            ['count' => $this->inbox->unreadCount($this->actor())],
            'Unread count retrieved successfully.',
        );
    }

    /**
     * Retrieve a single resource.
     *
     * @param  string  $notification
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'A single inbox notification.',
        type: 'array{success: true, message: string, data: InboxNotificationResource, meta: null, errors: null}',
    )]
    public function show(string $notification): JsonResponse
    {
        return $this->success(
            new InboxNotificationResource($this->inbox->findOwned($this->actor(), $notification)),
            'Notification retrieved successfully.',
        );
    }

    /**
     * Mark read.
     *
     * @param  string  $notification
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Notification marked as read.',
        type: 'array{success: true, message: string, data: InboxNotificationResource, meta: null, errors: null}',
    )]
    public function markRead(string $notification): JsonResponse
    {
        return $this->updated(
            new InboxNotificationResource($this->inbox->markAsRead($this->actor(), $notification)),
            'Notification marked as read.',
        );
    }

    /**
     * Mark unread.
     *
     * @param  string  $notification
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Notification marked as unread.',
        type: 'array{success: true, message: string, data: InboxNotificationResource, meta: null, errors: null}',
    )]
    public function markUnread(string $notification): JsonResponse
    {
        return $this->updated(
            new InboxNotificationResource($this->inbox->markAsUnread($this->actor(), $notification)),
            'Notification marked as unread.',
        );
    }

    /**
     * Mark all read.
     *
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'All notifications marked as read.',
        type: 'array{success: true, message: string, data: array{count: int}, meta: null, errors: null}',
    )]
    public function markAllRead(): JsonResponse
    {
        return $this->updated(
            ['count' => $this->inbox->markAllAsRead($this->actor())],
            'All notifications marked as read.',
        );
    }

    /**
     * Delete a resource.
     *
     * @param  string  $notification
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Notification deleted.',
        type: 'array{success: true, message: string, data: null, meta: null, errors: null}',
    )]
    public function destroy(string $notification): JsonResponse
    {
        $this->inbox->delete($this->actor(), $notification);

        return $this->deleted('Notification deleted successfully.');
    }

    /**
     * Resolve the authenticated tenant user.
     *
     * @return User
     */
    protected function actor(): User
    {
        /** @var User $user */
        $user = Auth::guard('tenant')->user();

        return $user;
    }
}
