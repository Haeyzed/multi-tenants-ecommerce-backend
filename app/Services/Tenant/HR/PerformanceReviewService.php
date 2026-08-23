<?php

declare(strict_types=1);

namespace App\Services\Tenant\HR;

use App\Enums\Tenant\HR\PerformanceCycleStatus;
use App\Enums\Tenant\HR\PerformanceReviewStatus;
use App\Models\Tenant\HR\PerformanceCycle;
use App\Models\Tenant\HR\PerformanceReview;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

/**
 * Employee performance reviews.
 */
class PerformanceReviewService
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
     * @param  array{employee_id?: int|null, performance_cycle_id?: int|null, status?: string|null, sort?: string|null, per_page?: int|null}  $params
     * @return LengthAwarePaginator<int, PerformanceReview>
     */
    public function list(array $params = []): LengthAwarePaginator
    {
        $this->hrSettings->assertPerformanceEnabled();

        return PerformanceReview::query()
            ->with(['cycle', 'employee.user', 'reviewer.user'])
            ->filter($params)
            ->applySort($params['sort'] ?? null)
            ->paginate($this->perPage($params));
    }

    /**
     * Create a resource.
     *
     * @param  array<string, mixed>  $data
     * @return PerformanceReview
     *
     * @throws ValidationException
     */
    public function store(array $data): PerformanceReview
    {
        $this->hrSettings->assertPerformanceEnabled();

        $cycle = PerformanceCycle::query()->findOrFail($data['performance_cycle_id']);
        $this->assertCycleWritable($cycle);
        $this->assertUnique($cycle->id, (int) $data['employee_id']);

        $status = $data['status'] ?? PerformanceReviewStatus::Draft;

        return PerformanceReview::query()->create([
            'performance_cycle_id' => $cycle->id,
            'employee_id' => $data['employee_id'],
            'reviewer_id' => $data['reviewer_id'] ?? null,
            'rating' => $data['rating'] ?? null,
            'summary' => $data['summary'] ?? null,
            'status' => $status,
            'submitted_at' => $status === PerformanceReviewStatus::Submitted || $status === PerformanceReviewStatus::Submitted->value
                ? now()
                : null,
        ])->load(['cycle', 'employee.user', 'reviewer.user']);
    }

    /**
     * Retrieve a single resource.
     *
     * @param  PerformanceReview  $review
     * @return PerformanceReview
     */
    public function show(PerformanceReview $review): PerformanceReview
    {
        $this->hrSettings->assertPerformanceEnabled();

        return $review->load(['cycle', 'employee.user', 'reviewer.user']);
    }

    /**
     * Update a resource.
     *
     * @param  PerformanceReview  $review
     * @param  array<string, mixed>  $data
     * @return PerformanceReview
     */
    public function update(PerformanceReview $review, array $data): PerformanceReview
    {
        $this->hrSettings->assertPerformanceEnabled();
        $this->assertCycleWritable($review->cycle ?? $review->cycle()->firstOrFail());

        unset($data['performance_cycle_id'], $data['employee_id']);

        $status = $data['status'] ?? $review->status;

        if ($status === PerformanceReviewStatus::Submitted || $status === PerformanceReviewStatus::Submitted->value) {
            $data['submitted_at'] = $review->submitted_at ?? now();
        }

        $review->fill($data);
        $review->save();

        return $review->fresh(['cycle', 'employee.user', 'reviewer.user']) ?? $review;
    }

    /**
     * Delete a resource.
     *
     * @param  PerformanceReview  $review
     * @return void
     */
    public function destroy(PerformanceReview $review): void
    {
        $this->hrSettings->assertPerformanceEnabled();
        $this->assertCycleWritable($review->cycle ?? $review->cycle()->firstOrFail());

        $review->delete();
    }

    /**
     * Assert cycle writable.
     *
     * @param  PerformanceCycle  $cycle
     * @return void
     *
     * @throws ValidationException
     */
    protected function assertCycleWritable(PerformanceCycle $cycle): void
    {
        if ($cycle->status === PerformanceCycleStatus::Closed) {
            throw ValidationException::withMessages([
                'performance_cycle_id' => ['Reviews cannot be changed on a closed cycle.'],
            ]);
        }
    }

    /**
     * Assert unique.
     *
     * @param  int  $cycleId
     * @param  int  $employeeId
     * @param  ?int  $ignoreId
     * @return void
     *
     * @throws ValidationException
     */
    protected function assertUnique(int $cycleId, int $employeeId, ?int $ignoreId = null): void
    {
        $exists = PerformanceReview::query()
            ->where('performance_cycle_id', $cycleId)
            ->where('employee_id', $employeeId)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'employee_id' => ['This employee already has a review in this cycle.'],
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
