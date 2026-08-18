<?php

declare(strict_types=1);

namespace App\Services\Tenant\HR;

use App\Enums\Tenant\HR\CandidateStatus;
use App\Enums\Tenant\HR\JobApplicationStatus;
use App\Enums\Tenant\HR\JobOfferStatus;
use App\Events\CandidateHired;
use App\Models\Tenant\Candidate;
use App\Models\Tenant\Employee;
use App\Models\Tenant\JobApplication;
use App\Models\Tenant\JobOffer;
use App\Models\Tenant\User;
use App\Services\Tenant\User\UserService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/**
 * Explicit candidate → employee conversion. Never runs automatically on offer accept.
 */
class HiringService
{
    public function __construct(
        private readonly HrSettingsService $hrSettings,
        private readonly EmployeeService $employees,
        private readonly EmployeeSalaryService $salaries,
        private readonly UserService $users,
        private readonly JobApplicationService $applications,
        private readonly RecruitmentStageService $stages,
        private readonly JobOfferService $offers,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function convert(JobApplication $application, User $actor, array $data = []): Employee
    {
        $this->hrSettings->assertRecruitmentEnabled();

        $application->load(['candidate', 'jobOpening', 'offers', 'hiredEmployee']);

        $offer = $this->acceptedOffer($application);
        $candidate = $application->candidate;

        if ($candidate === null) {
            throw ValidationException::withMessages([
                'candidate_id' => ['This application has no candidate to convert.'],
            ]);
        }

        return DB::transaction(function () use ($application, $candidate, $offer, $actor, $data): Employee {
            $locked = Candidate::query()->whereKey($candidate->id)->lockForUpdate()->firstOrFail();

            if ($locked->employee_id !== null) {
                $employee = Employee::query()->findOrFail($locked->employee_id);

                if ($application->hired_employee_id === null) {
                    $this->linkApplication($application, $employee, $actor);
                }

                return $employee->load(['user', 'department', 'designation']);
            }

            if ($application->hired_employee_id !== null) {
                throw ValidationException::withMessages([
                    'id' => ['This application has already been converted to an employee.'],
                ]);
            }

            $user = $this->resolveUser($locked, $data);
            $employee = Employee::query()->where('user_id', $user->id)->first();

            if ($employee === null) {
                $opening = $application->jobOpening;
                $employee = $this->employees->store([
                    'user_id' => $user->id,
                    'department_id' => $data['department_id'] ?? $opening?->department_id,
                    'designation_id' => $data['designation_id'] ?? $opening?->designation_id,
                    'job_title' => $data['job_title'] ?? $offer->position ?? $opening?->title,
                    'employment_type' => $data['employment_type'] ?? $opening?->employment_type,
                    'work_location' => $data['work_location'] ?? $opening?->work_location,
                    'hired_at' => $data['hired_at'] ?? $offer->start_date?->toDateString() ?? now()->toDateString(),
                    'notes' => $data['notes'] ?? null,
                ]);
            }

            $locked->employee_id = $employee->id;
            $locked->status = CandidateStatus::Hired;
            $locked->save();

            $this->linkApplication($application, $employee, $actor);
            $this->syncSalary($employee, $offer, $data);

            event(new CandidateHired($application->fresh(['candidate', 'hiredEmployee']) ?? $application, $employee));

            return $employee->load(['user', 'department', 'designation']);
        });
    }

    /**
     * @throws ValidationException
     */
    protected function acceptedOffer(JobApplication $application): JobOffer
    {
        $offer = $application->offers
            ->first(function (JobOffer $offer): bool {
                $this->offers->expireIfNeeded($offer);

                return $offer->status === JobOfferStatus::Accepted;
            });

        if ($offer === null) {
            throw ValidationException::withMessages([
                'id' => ['An accepted offer is required before converting a candidate to an employee.'],
            ]);
        }

        return $offer;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function resolveUser(Candidate $candidate, array $data): User
    {
        $existing = User::query()->where('email', $candidate->email)->first();

        if ($existing !== null) {
            return $existing;
        }

        $role = (string) ($data['role'] ?? $this->hrSettings->defaultHireRole());
        $roles = [];

        if (Role::query()->where('name', $role)->where('guard_name', 'tenant')->exists()) {
            $roles = [$role];
        }

        return $this->users->store([
            'first_name' => $candidate->first_name,
            'last_name' => $candidate->last_name,
            'email' => $candidate->email,
            'phone' => $candidate->phone,
            'password' => Str::password(16),
            'roles' => $roles,
        ]);
    }

    protected function linkApplication(JobApplication $application, Employee $employee, User $actor): void
    {
        $fromStage = $application->stage;
        $fromStatus = $application->status instanceof JobApplicationStatus
            ? $application->status
            : JobApplicationStatus::Offered;

        $application->hired_employee_id = $employee->id;
        $application->status = JobApplicationStatus::Hired;
        $stage = $this->stages->stageForKind(JobApplicationStatus::Hired);
        $application->recruitment_stage_id = $stage->id;
        $application->save();

        $this->applications->recordHistory(
            $application,
            $fromStage,
            $stage,
            $fromStatus,
            JobApplicationStatus::Hired,
            $actor,
            'Candidate converted to employee.',
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function syncSalary(Employee $employee, JobOffer $offer, array $data): void
    {
        if (! $this->hrSettings->isPayrollEnabled()) {
            return;
        }

        $salary = $data['base_salary'] ?? $offer->salary;

        if ($salary === null || bccomp((string) $salary, '0', 2) <= 0) {
            return;
        }

        $this->salaries->upsert($employee, [
            'base_salary' => $salary,
            'currency' => $data['currency'] ?? $offer->currency,
            'effective_from' => $data['hired_at'] ?? $offer->start_date?->toDateString() ?? now()->toDateString(),
        ]);
    }
}
