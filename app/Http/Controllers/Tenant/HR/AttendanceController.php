<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\HR\ClockAttendanceRequest;
use App\Http\Requests\Tenant\HR\IndexAttendanceRequest;
use App\Http\Requests\Tenant\HR\StoreAttendanceRequest;
use App\Http\Requests\Tenant\HR\UpdateAttendanceRequest;
use App\Http\Resources\Tenant\HR\AttendanceResource;
use App\Models\Tenant\Attendance;
use App\Models\Tenant\Employee;
use App\Models\Tenant\User;
use App\Services\Tenant\HR\AttendanceService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Tenant HR attendance endpoints.
 */
class AttendanceController extends Controller
{
    public function __construct(private readonly AttendanceService $attendanceService) {}

    #[Response(status: 200, description: 'Paginated attendance records.', type: 'array{success: true, message: string, data: AttendanceResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}')]
    public function index(IndexAttendanceRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Attendance::class);

        $params = $request->validated();
        $actor = $this->actor();

        if (! $actor->can('hr.attendance.view') && ! $actor->can('hr.attendance.manage') && ! $actor->can('hr.view')) {
            $params['employee_id'] = $actor->employee?->id;
        }

        $attendances = $this->attendanceService->list($params);

        return $this->success(
            AttendanceResource::collection($attendances->items()),
            'Attendance records retrieved successfully.',
            $this->paginationMeta($attendances),
        );
    }

    #[Response(status: 201, description: 'Created attendance record.', type: 'array{success: true, message: string, data: AttendanceResource, meta: null, errors: null}')]
    public function store(StoreAttendanceRequest $request): JsonResponse
    {
        $this->authorize('create', Attendance::class);

        return $this->created(
            new AttendanceResource($this->attendanceService->store($request->validated())),
            'Attendance recorded successfully.',
        );
    }

    #[Response(status: 200, description: 'An attendance record.', type: 'array{success: true, message: string, data: AttendanceResource, meta: null, errors: null}')]
    public function show(Attendance $attendance): JsonResponse
    {
        $this->authorize('view', $attendance);

        return $this->success(
            new AttendanceResource($this->attendanceService->show($attendance)),
            'Attendance retrieved successfully.',
        );
    }

    #[Response(status: 200, description: 'Updated attendance record.', type: 'array{success: true, message: string, data: AttendanceResource, meta: null, errors: null}')]
    public function update(UpdateAttendanceRequest $request, Attendance $attendance): JsonResponse
    {
        $this->authorize('update', $attendance);

        return $this->updated(
            new AttendanceResource($this->attendanceService->update($attendance, $request->validated())),
            'Attendance updated successfully.',
        );
    }

    #[Response(status: 200, description: 'Deleted attendance record.', type: 'array{success: true, message: string, data: null, meta: null, errors: null}')]
    public function destroy(Attendance $attendance): JsonResponse
    {
        $this->authorize('delete', $attendance);
        $this->attendanceService->destroy($attendance);

        return $this->deleted('Attendance deleted successfully.');
    }

    #[Response(status: 200, description: 'Clock-in record.', type: 'array{success: true, message: string, data: AttendanceResource, meta: null, errors: null}')]
    public function clockIn(ClockAttendanceRequest $request): JsonResponse
    {
        $this->authorize('clock', Attendance::class);

        $attendance = $this->attendanceService->clockIn(
            $this->resolveEmployee(
                isset($request->validated()['employee_id']) ? (int) $request->validated('employee_id') : null,
            ),
        );

        return $this->success(
            new AttendanceResource($attendance),
            'Clocked in successfully.',
        );
    }

    #[Response(status: 200, description: 'Clock-out record.', type: 'array{success: true, message: string, data: AttendanceResource, meta: null, errors: null}')]
    public function clockOut(ClockAttendanceRequest $request): JsonResponse
    {
        $this->authorize('clock', Attendance::class);

        $attendance = $this->attendanceService->clockOut(
            $this->resolveEmployee(
                isset($request->validated()['employee_id']) ? (int) $request->validated('employee_id') : null,
            ),
        );

        return $this->success(
            new AttendanceResource($attendance),
            'Clocked out successfully.',
        );
    }

    /**
     * @throws ValidationException
     */
    protected function resolveEmployee(?int $employeeId): Employee
    {
        $actor = $this->actor();

        if ($employeeId !== null) {
            if (! $actor->can('hr.attendance.manage') && $actor->employee?->id !== $employeeId) {
                throw ValidationException::withMessages([
                    'employee_id' => ['You may only clock attendance for your own employee profile.'],
                ]);
            }

            return Employee::query()->findOrFail($employeeId);
        }

        $employee = $actor->employee;

        if ($employee === null) {
            throw ValidationException::withMessages([
                'employee_id' => ['The authenticated user does not have an employee profile.'],
            ]);
        }

        return $employee;
    }

    protected function actor(): User
    {
        /** @var User $user */
        $user = Auth::guard('tenant')->user();

        return $user;
    }
}
