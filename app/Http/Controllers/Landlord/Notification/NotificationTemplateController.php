<?php

declare(strict_types=1);

namespace App\Http\Controllers\Landlord\Notification;

use App\Http\Controllers\Controller;
use App\Http\Requests\Landlord\Notification\IndexNotificationTemplateRequest;
use App\Http\Requests\Landlord\Notification\PreviewNotificationTemplateRequest;
use App\Http\Requests\Landlord\Notification\StoreNotificationTemplateRequest;
use App\Http\Requests\Landlord\Notification\UpdateNotificationTemplateRequest;
use App\Http\Resources\Landlord\Notification\NotificationTemplateResource;
use App\Models\Landlord\NotificationTemplate;
use App\Services\Notification\NotificationTemplateService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

/**
 * Landlord notification template administration.
 */
class NotificationTemplateController extends Controller
{
    public function __construct(private readonly NotificationTemplateService $templates) {}

    #[Response(
        status: 200,
        description: 'Paginated notification templates.',
        type: 'array{success: true, message: string, data: NotificationTemplateResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}',
    )]
    public function index(IndexNotificationTemplateRequest $request): JsonResponse
    {
        $templates = $this->templates->list($request->validated());

        return $this->success(
            NotificationTemplateResource::collection($templates->items()),
            'Notification templates retrieved successfully.',
            $this->paginationMeta($templates),
        );
    }

    #[Response(
        status: 200,
        description: 'Notification template options.',
        type: ApiResponseSchema::OPTIONS,
    )]
    public function options(IndexNotificationTemplateRequest $request): JsonResponse
    {
        return $this->success(
            $this->templates->options($request->validated()),
            'Notification template options retrieved successfully.',
        );
    }

    #[Response(
        status: 201,
        description: 'Created notification template.',
        type: 'array{success: true, message: string, data: NotificationTemplateResource, meta: null, errors: null}',
    )]
    public function store(StoreNotificationTemplateRequest $request): JsonResponse
    {
        try {
            $template = $this->templates->store($request->validated());
        } catch (InvalidArgumentException $exception) {
            return $this->error($exception->getMessage(), 422);
        }

        return $this->created(
            new NotificationTemplateResource($template),
            'Notification template created successfully.',
        );
    }

    #[Response(
        status: 200,
        description: 'A notification template.',
        type: 'array{success: true, message: string, data: NotificationTemplateResource, meta: null, errors: null}',
    )]
    public function show(NotificationTemplate $notificationTemplate): JsonResponse
    {
        return $this->success(
            new NotificationTemplateResource($notificationTemplate),
            'Notification template retrieved successfully.',
        );
    }

    #[Response(
        status: 200,
        description: 'Updated notification template.',
        type: 'array{success: true, message: string, data: NotificationTemplateResource, meta: null, errors: null}',
    )]
    public function update(UpdateNotificationTemplateRequest $request, NotificationTemplate $notificationTemplate): JsonResponse
    {
        try {
            $template = $this->templates->update($notificationTemplate, $request->validated());
        } catch (InvalidArgumentException $exception) {
            return $this->error($exception->getMessage(), 422);
        }

        return $this->updated(
            new NotificationTemplateResource($template),
            'Notification template updated successfully.',
        );
    }

    #[Response(
        status: 200,
        description: 'Deleted notification template.',
        type: 'array{success: true, message: string, data: null, meta: null, errors: null}',
    )]
    public function destroy(NotificationTemplate $notificationTemplate): JsonResponse
    {
        $this->templates->delete($notificationTemplate);

        return $this->deleted('Notification template deleted successfully.');
    }

    #[Response(
        status: 200,
        description: 'Rendered notification template preview.',
        type: 'array{success: true, message: string, data: array, meta: null, errors: null}',
    )]
    public function preview(PreviewNotificationTemplateRequest $request, NotificationTemplate $notificationTemplate): JsonResponse
    {
        try {
            $preview = $this->templates->preview(
                $notificationTemplate,
                $request->validated('data') ?? [],
            );
        } catch (InvalidArgumentException $exception) {
            return $this->error($exception->getMessage(), 422);
        }

        return $this->success($preview, 'Notification template preview rendered successfully.');
    }
}
