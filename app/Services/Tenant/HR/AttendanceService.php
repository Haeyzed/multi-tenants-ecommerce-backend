<?php

declare(strict_types=1);

namespace App\Services\Tenant\HR;

use App\Enums\Tenant\HR\AttendanceClockSource;
use App\Enums\Tenant\HR\AttendanceStatus;
use App\Models\Tenant\Attendance;
use App\Models\Tenant\Employee;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Daily attendance records and clock in/out.
 */
class AttendanceService
{
    public function __construct(
        private readonly HrSettingsService $hrSettings,
        private readonly WorkCalendarService $calendar,
        private readonly OvertimeEngine $overtime,
    ) {}

    /**
     * @param  array{
     *     employee_id?: int|null,
     *     status?: string|null,
     *     from?: string|null,
     *     to?: string|null,
     *     sort?: string|null,
     *     per_page?: int|null
     * }  $params
     * @return LengthAwarePaginator<int, Attendance>
     */
    public function list(array $params = []): LengthAwarePaginator
    {
        return Attendance::query()
            ->with(['employee.user'])
            ->filter($params)
            ->applySort($params['sort'] ?? null)
            ->paginate($this->perPage($params));
    }

    /**
     * @param  array{
     *     employee_id: int,
     *     work_date?: string|null,
     *     status?: AttendanceStatus|string|null,
     *     checked_in_at?: string|null,
     *     checked_out_at?: string|null,
     *     notes?: string|null
     * }  $data
     *
     * @throws ValidationException
     */
    public function store(array $data): Attendance
    {
        $this->hrSettings->assertAttendanceEnabled();

        $employee = Employee::query()->findOrFail($data['employee_id']);
        $workDate = $data['work_date'] ?? now()->toDateString();

        if (Attendance::query()->where('employee_id', $employee->id)->whereDate('work_date', $workDate)->exists()) {
            throw ValidationException::withMessages([
                'work_date' => ['An attendance record already exists for this employee on that date.'],
            ]);
        }

        return Attendance::query()->create([
            'employee_id' => $employee->id,
            'work_date' => $workDate,
            'status' => $data['status'] ?? AttendanceStatus::Present,
            'checked_in_at' => $data['checked_in_at'] ?? null,
            'checked_out_at' => $data['checked_out_at'] ?? null,
            'overtime_minutes' => $data['overtime_minutes'] ?? 0,
            'overtime_rate_percent' => $data['overtime_rate_percent'] ?? 0,
            'clock_source' => $data['clock_source'] ?? AttendanceClockSource::Manual,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'accuracy_meters' => $data['accuracy_meters'] ?? null,
            'device_id' => $data['device_id'] ?? null,
            'biometric_hash' => isset($data['biometric_token']) ? hash('sha256', (string) $data['biometric_token']) : null,
            'notes' => $data['notes'] ?? null,
        ])->load(['employee.user']);
    }

    public function show(Attendance $attendance): Attendance
    {
        return $attendance->load(['employee.user']);
    }

    /**
     * @param  array{
     *     status?: AttendanceStatus|string,
     *     checked_in_at?: string|null,
     *     checked_out_at?: string|null,
     *     notes?: string|null
     * }  $data
     */
    public function update(Attendance $attendance, array $data): Attendance
    {
        $this->hrSettings->assertAttendanceEnabled();

        unset($data['employee_id'], $data['work_date']);

        $attendance->fill($data);
        $attendance->save();

        return $attendance->fresh(['employee.user']) ?? $attendance;
    }

    public function destroy(Attendance $attendance): void
    {
        $this->hrSettings->assertAttendanceEnabled();

        $attendance->delete();
    }

    /**
     * Clock the employee in for today.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function clockIn(Employee $employee, array $data = []): Attendance
    {
        $this->hrSettings->assertAttendanceEnabled();
        $evidence = $this->clockEvidence($data);

        $now = now();

        if (! $this->calendar->isWorkingDate($employee, $now)) {
            throw ValidationException::withMessages([
                'employee_id' => ['Today is not a configured working day.'],
            ]);
        }

        $today = $now->toDateString();
        $existing = Attendance::query()
            ->where('employee_id', $employee->id)
            ->whereDate('work_date', $today)
            ->first();

        if ($existing !== null && $existing->checked_in_at !== null) {
            throw ValidationException::withMessages([
                'employee_id' => ['This employee is already clocked in for today.'],
            ]);
        }

        $status = $this->clockInStatus($employee, $now);
        $payload = array_merge($evidence, [
            'status' => $status,
            'checked_in_at' => $now,
        ]);

        if ($existing !== null) {
            $existing->fill($payload);
            $existing->save();

            return $existing->fresh(['employee.user']) ?? $existing;
        }

        return Attendance::query()->create(array_merge($payload, [
            'employee_id' => $employee->id,
            'work_date' => $today,
        ]))->load(['employee.user']);
    }

    /**
     * Clock the employee out for today.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function clockOut(Employee $employee, array $data = []): Attendance
    {
        $this->hrSettings->assertAttendanceEnabled();
        $evidence = $this->clockEvidence($data);

        $attendance = Attendance::query()
            ->where('employee_id', $employee->id)
            ->whereDate('work_date', now()->toDateString())
            ->first();

        if ($attendance === null || $attendance->checked_in_at === null) {
            throw ValidationException::withMessages([
                'employee_id' => ['This employee is not clocked in for today.'],
            ]);
        }

        if ($attendance->checked_out_at !== null) {
            throw ValidationException::withMessages([
                'employee_id' => ['This employee is already clocked out for today.'],
            ]);
        }

        $attendance->fill($evidence);
        $attendance->checked_out_at = now();

        if ($this->hrSettings->isOvertimeEnabled() && (int) $attendance->overtime_minutes === 0) {
            $attendance->overtime_minutes = $this->overtime->overtimeMinutes($employee, $attendance);
            $attendance->overtime_rate_percent = $this->overtime->ratePercent($employee, $attendance->work_date);
        }

        $attendance->save();

        return $attendance->fresh(['employee.user']) ?? $attendance;
    }

    protected function clockInStatus(Employee $employee, Carbon $now): AttendanceStatus
    {
        $start = Carbon::parse($now->toDateString().' '.$this->calendar->startTime($employee, $now));
        $threshold = $start->copy()->addMinutes($this->hrSettings->lateToleranceMinutes());

        return $now->gt($threshold) ? AttendanceStatus::Late : AttendanceStatus::Present;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    protected function clockEvidence(array $data): array
    {
        $latitude = $data['latitude'] ?? null;
        $longitude = $data['longitude'] ?? null;
        $token = isset($data['biometric_token']) ? trim((string) $data['biometric_token']) : '';

        if ($this->hrSettings->gpsRequired() && ($latitude === null || $longitude === null)) {
            throw ValidationException::withMessages([
                'latitude' => ['GPS coordinates are required to clock attendance.'],
            ]);
        }

        if ($this->hrSettings->biometricRequired() && $token === '') {
            throw ValidationException::withMessages([
                'biometric_token' => ['A biometric token is required to clock attendance.'],
            ]);
        }

        $this->assertWithinGeofence(
            $latitude === null ? null : (float) $latitude,
            $longitude === null ? null : (float) $longitude,
        );

        $source = AttendanceClockSource::Web;

        if ($token !== '') {
            $source = AttendanceClockSource::Biometric;
        } elseif ($latitude !== null && $longitude !== null) {
            $source = AttendanceClockSource::Gps;
        }

        return [
            'clock_source' => $source,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'accuracy_meters' => $data['accuracy_meters'] ?? null,
            'device_id' => $data['device_id'] ?? null,
            'biometric_hash' => $token === '' ? null : hash('sha256', $token),
        ];
    }

    /**
     * @throws ValidationException
     */
    protected function assertWithinGeofence(?float $latitude, ?float $longitude): void
    {
        $radius = $this->hrSettings->geofenceRadiusMeters();
        $officeLat = $this->hrSettings->geofenceLatitude();
        $officeLng = $this->hrSettings->geofenceLongitude();

        if ($radius <= 0 || $officeLat === null || $officeLng === null || $latitude === null || $longitude === null) {
            return;
        }

        if ($this->metersBetween($officeLat, $officeLng, $latitude, $longitude) > $radius) {
            throw ValidationException::withMessages([
                'latitude' => ['Clock location is outside the configured workplace geofence.'],
            ]);
        }
    }

    protected function metersBetween(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earth = 6371000.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = (sin($dLat / 2) ** 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * (sin($dLng / 2) ** 2);

        return 2 * $earth * asin(min(1, sqrt($a)));
    }

    /**
     * @param  array{per_page?: int|null}  $params
     */
    protected function perPage(array $params): int
    {
        return max(1, min((int) ($params['per_page'] ?? 15), 100));
    }
}
