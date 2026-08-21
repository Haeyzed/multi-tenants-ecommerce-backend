<?php

declare(strict_types=1);

namespace App\Services\Tenant\HR;

use App\Enums\Tenant\HR\PerformanceCycleStatus;
use App\Models\HR\PerformanceCycle;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

/**
 * Performance review cycles.
 */
class PerformanceCycleService
{
    public function __construct(private readonly HrSettingsService $hrSettings) {}

    /**
     * @param  array{search?: string|null, status?: string|null, sort?: string|null, per_page?: int|null}  $params
     * @return LengthAwarePaginator<int, PerformanceCycle>
     */
    public function list(array $params = []): LengthAwarePaginator
    {
        $this->hrSettings->assertPerformanceEnabled();

        return PerformanceCycle::query()
            ->withCount('reviews')
            ->filter($params)
            ->applySort($params['sort'] ?? null)
            ->paginate($this->perPage($params));
    }

    /**
     * @param  array{name: string, starts_on: string, ends_on: string, status?: PerformanceCycleStatus|string|null, description?: string|null}  $data
     */
    public function store(array $data): PerformanceCycle
    {
        $this->hrSettings->assertPerformanceEnabled();
        $this->assertWindow($data['starts_on'], $data['ends_on']);

        return PerformanceCycle::query()->create([
            'name' => $data['name'],
            'starts_on' => $data['starts_on'],
            'ends_on' => $data['ends_on'],
            'status' => $data['status'] ?? PerformanceCycleStatus::Draft,
            'description' => $data['description'] ?? null,
        ]);
    }

    public function show(PerformanceCycle $cycle): PerformanceCycle
    {
        $this->hrSettings->assertPerformanceEnabled();

        return $cycle->loadCount('reviews');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(PerformanceCycle $cycle, array $data): PerformanceCycle
    {
        $this->hrSettings->assertPerformanceEnabled();

        $start = $data['starts_on'] ?? $cycle->starts_on?->toDateString();
        $end = $data['ends_on'] ?? $cycle->ends_on?->toDateString();
        $this->assertWindow((string) $start, (string) $end);

        $cycle->fill($data);
        $cycle->save();

        return $cycle;
    }

    /**
     * @throws ValidationException
     */
    public function destroy(PerformanceCycle $cycle): void
    {
        $this->hrSettings->assertPerformanceEnabled();

        if ($cycle->reviews()->exists()) {
            throw ValidationException::withMessages([
                'id' => ['This performance cycle has reviews and cannot be deleted.'],
            ]);
        }

        $cycle->delete();
    }

    /**
     * @throws ValidationException
     */
    protected function assertWindow(string $start, string $end): void
    {
        if ($end < $start) {
            throw ValidationException::withMessages([
                'ends_on' => ['The cycle end date must be on or after the start date.'],
            ]);
        }
    }

    /**
     * @param  array{per_page?: int|null}  $params
     */
    protected function perPage(array $params): int
    {
        return max(1, min((int) ($params['per_page'] ?? 15), 100));
    }
}
