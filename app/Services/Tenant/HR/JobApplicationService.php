<?php

declare(strict_types=1);

namespace App\Services\Tenant\HR;

use App\Enums\Tenant\HR\JobApplicationStatus;
use App\Enums\Tenant\HR\JobOpeningStatus;
use App\Models\Tenant\JobApplication;
use App\Models\Tenant\JobOpening;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

/**
 * Recruitment applications.
 */
class JobApplicationService
{
    public function __construct(private readonly HrSettingsService $hrSettings) {}

    /**
     * @param  array{search?: string|null, status?: string|null, job_opening_id?: int|null, sort?: string|null, per_page?: int|null}  $params
     * @return LengthAwarePaginator<int, JobApplication>
     */
    public function list(array $params = []): LengthAwarePaginator
    {
        $this->hrSettings->assertRecruitmentEnabled();

        return JobApplication::query()
            ->with(['jobOpening', 'hiredEmployee.user'])
            ->filter($params)
            ->applySort($params['sort'] ?? null)
            ->paginate($this->perPage($params));
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function store(array $data): JobApplication
    {
        $this->hrSettings->assertRecruitmentEnabled();

        $opening = JobOpening::query()->findOrFail($data['job_opening_id']);

        if ($opening->status !== JobOpeningStatus::Open) {
            throw ValidationException::withMessages([
                'job_opening_id' => ['Applications can only be submitted to open job openings.'],
            ]);
        }

        return JobApplication::query()->create([
            'job_opening_id' => $opening->id,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => strtolower((string) $data['email']),
            'phone' => $data['phone'] ?? null,
            'status' => $data['status'] ?? JobApplicationStatus::Received,
            'cover_letter' => $data['cover_letter'] ?? null,
            'notes' => $data['notes'] ?? null,
        ])->load(['jobOpening', 'hiredEmployee.user']);
    }

    public function show(JobApplication $application): JobApplication
    {
        $this->hrSettings->assertRecruitmentEnabled();

        return $application->load(['jobOpening', 'hiredEmployee.user']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(JobApplication $application, array $data): JobApplication
    {
        $this->hrSettings->assertRecruitmentEnabled();

        unset($data['job_opening_id']);

        if (isset($data['email'])) {
            $data['email'] = strtolower((string) $data['email']);
        }

        if (($data['status'] ?? null) instanceof JobApplicationStatus
            && $data['status'] !== JobApplicationStatus::Hired) {
            $data['hired_employee_id'] = null;
        }

        $application->fill($data);
        $application->save();

        return $application->fresh(['jobOpening', 'hiredEmployee.user']) ?? $application;
    }

    public function destroy(JobApplication $application): void
    {
        $this->hrSettings->assertRecruitmentEnabled();

        $application->delete();
    }

    /**
     * @param  array{per_page?: int|null}  $params
     */
    protected function perPage(array $params): int
    {
        return max(1, min((int) ($params['per_page'] ?? 15), 100));
    }
}
