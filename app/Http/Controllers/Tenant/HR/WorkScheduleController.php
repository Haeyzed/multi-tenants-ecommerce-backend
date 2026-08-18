<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\HR\IndexWorkScheduleRequest;
use App\Http\Requests\Tenant\HR\StoreWorkScheduleRequest;
use App\Http\Requests\Tenant\HR\UpdateWorkScheduleRequest;
use App\Http\Resources\Tenant\HR\WorkScheduleResource;
use App\Models\Tenant\WorkSchedule;
use App\Services\Tenant\HR\WorkScheduleService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

/**
 * Employee work schedules.
 */
#[Group('HR')]
class WorkScheduleController extends Controller
{
    public function __construct(private readonly WorkScheduleService $workSchedules) {}

    #[Response(status: 200, description: 'Paginated work schedules.', type: 'array{success: true, message: string, data: WorkScheduleResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}')]
    public function index(IndexWorkScheduleRequest $request): JsonResponse
    {
        $this->authorize('viewAny', WorkSchedule::class);

        $schedules = $this->workSchedules->list($request->validated());

        return $this->success(
            WorkScheduleResource::collection($schedules->items()),
            'Work schedules retrieved successfully.',
            $this->paginationMeta($schedules),
        );
    }

    #[Response(status: 200, description: 'Work schedule options.', type: ApiResponseSchema::OPTIONS)]
    public function options(): JsonResponse
    {
        $this->authorize('viewAny', WorkSchedule::class);

        return $this->success(
            $this->workSchedules->options(),
            'Work schedule options retrieved successfully.',
        );
    }

    #[Response(status: 201, description: 'Created work schedule.', type: 'array{success: true, message: string, data: WorkScheduleResource, meta: null, errors: null}')]
    public function store(StoreWorkScheduleRequest $request): JsonResponse
    {
        $this->authorize('create', WorkSchedule::class);

        return $this->created(
            new WorkScheduleResource($this->workSchedules->store($request->validated())),
            'Work schedule created successfully.',
        );
    }

    #[Response(status: 200, description: 'A work schedule.', type: 'array{success: true, message: string, data: WorkScheduleResource, meta: null, errors: null}')]
    public function show(WorkSchedule $work_schedule): JsonResponse
    {
        $this->authorize('view', $work_schedule);

        return $this->success(
            new WorkScheduleResource($this->workSchedules->show($work_schedule)),
            'Work schedule retrieved successfully.',
        );
    }

    #[Response(status: 200, description: 'Updated work schedule.', type: 'array{success: true, message: string, data: WorkScheduleResource, meta: null, errors: null}')]
    public function update(UpdateWorkScheduleRequest $request, WorkSchedule $work_schedule): JsonResponse
    {
        $this->authorize('update', $work_schedule);

        return $this->updated(
            new WorkScheduleResource($this->workSchedules->update($work_schedule, $request->validated())),
            'Work schedule updated successfully.',
        );
    }

    #[Response(status: 200, description: 'Deleted work schedule.', type: 'array{success: true, message: string, data: null, meta: null, errors: null}')]
    public function destroy(WorkSchedule $work_schedule): JsonResponse
    {
        $this->authorize('delete', $work_schedule);
        $this->workSchedules->destroy($work_schedule);

        return $this->deleted('Work schedule deleted successfully.');
    }
}
