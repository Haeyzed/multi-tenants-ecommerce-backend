<?php

declare(strict_types=1);

namespace App\Services\Tenant\HR;

use App\Models\HR\Employee;
use App\Models\HR\HrActivity;
use App\Models\HR\LeaveRequest;
use App\Models\HR\PayrollRun;
use App\Models\Tenant\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Lightweight HR audit log for employees, leave, and payroll.
 * Omits bank details, salary amounts, free-text notes, and tax identifiers.
 */
class HrActivityService
{
    /**
     * Record.
     *
     * @param  Model  $subject
     * @param  string  $action
     * @param  ?User  $actor
     * @param  array<string, mixed>  $meta
     * @return void
     */
    public function record(Model $subject, string $action, ?User $actor = null, array $meta = []): void
    {
        if (! Schema::hasTable('hr_activities')) {
            return;
        }

        HrActivity::query()->create([
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'action' => $action,
            'actor_id' => $actor?->id,
            'meta' => $this->sanitize($meta),
        ]);
    }

    /**
     * List for employee.
     *
     * @param  Employee  $employee
     * @param  array{sort?: string|null, per_page?: int|null}  $params
     * @return LengthAwarePaginator<int, HrActivity>
     */
    public function listForEmployee(Employee $employee, array $params = []): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($params['per_page'] ?? 15), 100));

        if (! Schema::hasTable('hr_activities')) {
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage);
        }

        $leaveIds = $employee->leaveRequests()->pluck('id')->all();

        $query = HrActivity::query()
            ->with('actor:id,first_name,last_name,email')
            ->where(function ($query) use ($employee, $leaveIds): void {
                $query->where(function ($query) use ($employee): void {
                    $query->where('subject_type', $employee->getMorphClass())
                        ->where('subject_id', $employee->getKey());
                });

                if ($leaveIds !== []) {
                    $query->orWhere(function ($query) use ($leaveIds): void {
                        $query->where('subject_type', (new LeaveRequest)->getMorphClass())
                            ->whereIn('subject_id', $leaveIds);
                    });
                }
            });

        $sort = $params['sort'] ?? '-created_at';
        $direction = str_starts_with((string) $sort, '-') ? 'desc' : 'asc';
        $column = ltrim((string) $sort, '-');

        if (! in_array($column, ['created_at', 'id', 'action'], true)) {
            $column = 'created_at';
            $direction = 'desc';
        }

        return $query->orderBy($column, $direction)->orderBy('id')->paginate($perPage);
    }

    /**
     * List for payroll run.
     *
     * @param  PayrollRun  $payrollRun
     * @param  array{sort?: string|null, per_page?: int|null}  $params
     * @return LengthAwarePaginator<int, HrActivity>
     */
    public function listForPayrollRun(PayrollRun $payrollRun, array $params = []): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($params['per_page'] ?? 15), 100));

        if (! Schema::hasTable('hr_activities')) {
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage);
        }

        return HrActivity::query()
            ->with('actor:id,first_name,last_name,email')
            ->where('subject_type', $payrollRun->getMorphClass())
            ->where('subject_id', $payrollRun->getKey())
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * Sanitize.
     *
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    protected function sanitize(array $meta): array
    {
        unset(
            $meta['salary'],
            $meta['gross'],
            $meta['net'],
            $meta['amount'],
            $meta['notes'],
            $meta['reason'],
            $meta['review_notes'],
            $meta['bank_name'],
            $meta['bank_code'],
            $meta['account_number'],
            $meta['account_name'],
            $meta['tax_id'],
            $meta['pension_pin'],
            $meta['nhf_number'],
            $meta['nsitf_number'],
            $meta['password'],
            $meta['credentials'],
        );

        return $meta;
    }
}
