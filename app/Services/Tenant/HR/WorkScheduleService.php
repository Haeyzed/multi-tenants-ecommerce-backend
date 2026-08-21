<?php

declare(strict_types=1);

namespace App\Services\Tenant\HR;

use App\Models\HR\WorkSchedule;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Employee work-schedule CRUD.
 */
class WorkScheduleService
{
    /**
     * Retrieve a paginated list of resources.
     *
     * @param  array{search?: string|null, is_active?: bool|null, sort?: string|null, per_page?: int|null}  $params
     * @return LengthAwarePaginator<int, WorkSchedule>
     */
    public function list(array $params = []): LengthAwarePaginator
    {
        return WorkSchedule::query()
            ->with(['days', 'overtimePolicy'])
            ->filter($params)
            ->applySort($params['sort'] ?? null)
            ->paginate($this->perPage($params));
    }

    /**
     * Return options for select inputs.
     *
     * @return Collection<int, array{label: string, value: int, id: int}>
     */
    public function options(): Collection
    {
        return WorkSchedule::query()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (WorkSchedule $schedule): array => [
                'label' => $schedule->name,
                'value' => $schedule->id,
                'id' => $schedule->id,
            ])
            ->values();
    }

    /**
     * name: string, code?: string|null, is_default?: bool, is_active?: bool, overtime_policy_id?: int|null, days?: list<array{weekday: int, start_time: string, end_time: string, break_minutes?: int}> }  $data
     *
     * @param  array{
     *     name: string,
     *     code?: string|null,
     *     is_default?: bool,
     *     is_active?: bool,
     *     overtime_policy_id?: int|null,
     *     days?: list<array{weekday: int, start_time: string, end_time: string, break_minutes?: int}>
     * }  $data
     * @return WorkSchedule
     */
    public function store(array $data): WorkSchedule
    {
        $schedule = WorkSchedule::query()->create([
            'name' => $data['name'],
            'code' => $this->nullableCode($data['code'] ?? null),
            'is_default' => (bool) ($data['is_default'] ?? false),
            'is_active' => $data['is_active'] ?? true,
            'overtime_policy_id' => $data['overtime_policy_id'] ?? null,
        ]);

        $this->syncDays($schedule, $data['days'] ?? []);
        $this->ensureSingleDefault($schedule);

        return $this->show($schedule);
    }

    /**
     * Retrieve a single resource.
     *
     * @param  WorkSchedule  $schedule
     * @return WorkSchedule
     */
    public function show(WorkSchedule $schedule): WorkSchedule
    {
        return $schedule->load(['days', 'overtimePolicy']);
    }

    /**
     * Update a resource.
     *
     * @param  WorkSchedule  $schedule
     * @param  array<string, mixed>  $data
     * @return WorkSchedule
     */
    public function update(WorkSchedule $schedule, array $data): WorkSchedule
    {
        if (array_key_exists('code', $data)) {
            $data['code'] = $this->nullableCode($data['code']);
        }

        $days = $data['days'] ?? null;
        unset($data['days']);

        $schedule->fill($data);
        $schedule->save();

        if (is_array($days)) {
            $this->syncDays($schedule, $days);
        }

        $this->ensureSingleDefault($schedule);

        return $this->show($schedule);
    }

    /**
     * Delete a resource.
     *
     * @param  WorkSchedule  $schedule
     * @return void
     *
     * @throws ValidationException
     */
    public function destroy(WorkSchedule $schedule): void
    {
        if ($schedule->employees()->exists()) {
            throw ValidationException::withMessages([
                'id' => ['This work schedule is assigned to employees and cannot be deleted.'],
            ]);
        }

        $schedule->days()->delete();
        $schedule->delete();
    }

    /**
     * Sync days.
     *
     * @param  WorkSchedule  $schedule
     * @param  list<array{weekday: int, start_time: string, end_time: string, break_minutes?: int}>  $days
     * @return void
     */
    protected function syncDays(WorkSchedule $schedule, array $days): void
    {
        $schedule->days()->delete();

        foreach ($days as $day) {
            $schedule->days()->create([
                'weekday' => (int) $day['weekday'],
                'start_time' => $day['start_time'],
                'end_time' => $day['end_time'],
                'break_minutes' => (int) ($day['break_minutes'] ?? 0),
            ]);
        }
    }

    /**
     * Ensure single default.
     *
     * @param  WorkSchedule  $schedule
     * @return void
     */
    protected function ensureSingleDefault(WorkSchedule $schedule): void
    {
        if (! $schedule->is_default) {
            return;
        }

        WorkSchedule::query()->whereKeyNot($schedule->id)->update(['is_default' => false]);
    }

    /**
     * Nullable code.
     *
     * @param  mixed  $code
     * @return ?string
     */
    protected function nullableCode(mixed $code): ?string
    {
        $code = is_string($code) ? strtolower(trim($code)) : '';

        return $code === '' ? null : $code;
    }

    /**
     * Resolve the page size for paginated listings.
     *
     * @param  array{per_page?: int|null}  $params
     * @return int
     */
    protected function perPage(array $params): int
    {
        return max(1, min((int) ($params['per_page'] ?? 15), 100));
    }
}
