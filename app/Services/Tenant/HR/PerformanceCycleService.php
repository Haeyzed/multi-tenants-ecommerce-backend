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
    /**
     * Create a new class instance.
     *
     * @param  HrSettingsService  $hrSettings
     */
    public function __construct(private readonly HrSettingsService $hrSettings) {}

    /**
     * Retrieve a paginated list of resources.
     *
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
     * Create a resource.
     *
     * @param  array{name: string, starts_on: string, ends_on: string, status?: PerformanceCycleStatus|string|null, description?: string|null}  $data
     * @return PerformanceCycle
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

    /**
     * Retrieve a single resource.
     *
     * @param  PerformanceCycle  $cycle
     * @return PerformanceCycle
     */
    public function show(PerformanceCycle $cycle): PerformanceCycle
    {
        $this->hrSettings->assertPerformanceEnabled();

        return $cycle->loadCount('reviews');
    }

    /**
     * Update a resource.
     *
     * @param  PerformanceCycle  $cycle
     * @param  array<string, mixed>  $data
     * @return PerformanceCycle
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
     * Delete a resource.
     *
     * @param  PerformanceCycle  $cycle
     * @return void
     *
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
     * Assert window.
     *
     * @param  string  $start
     * @param  string  $end
     * @return void
     *
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
