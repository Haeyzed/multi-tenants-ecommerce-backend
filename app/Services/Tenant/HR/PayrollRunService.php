<?php

declare(strict_types=1);

namespace App\Services\Tenant\HR;

use App\Enums\Tenant\HR\AttendanceStatus;
use App\Enums\Tenant\HR\EmploymentStatus;
use App\Enums\Tenant\HR\LeaveStatus;
use App\Enums\Tenant\HR\PayFrequency;
use App\Enums\Tenant\HR\PayrollLineType;
use App\Enums\Tenant\HR\PayrollPeriodStatus;
use App\Enums\Tenant\HR\PayrollRunStatus;
use App\Enums\Tenant\HR\SalaryComponentCalculation;
use App\Events\PayrollPaid;
use App\Events\PayrollProcessed;
use App\Events\PayslipAvailable;
use App\Models\Tenant\Account;
use App\Models\Tenant\Attendance;
use App\Models\Tenant\Employee;
use App\Models\Tenant\EmployeeSalaryComponent;
use App\Models\Tenant\JournalEntry;
use App\Models\Tenant\LeaveRequest;
use App\Models\Tenant\LeaveType;
use App\Models\Tenant\PayrollItem;
use App\Models\Tenant\PayrollItemLine;
use App\Models\Tenant\PayrollPeriod;
use App\Models\Tenant\PayrollRun;
use App\Models\Tenant\User;
use App\Services\Tenant\Accounting\JournalEntryService;
use App\Support\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Payroll run lifecycle: draft, generate, process, pay, cancel.
 */
class PayrollRunService
{
    public function __construct(
        private readonly JournalEntryService $journalEntryService,
        private readonly HrSettingsService $hrSettings,
        private readonly LeaveTypeService $leaveTypeService,
    ) {}

    /**
     * @param  array{
     *     status?: string|null,
     *     from?: string|null,
     *     to?: string|null,
     *     sort?: string|null,
     *     per_page?: int|null
     * }  $params
     * @return LengthAwarePaginator<int, PayrollRun>
     */
    public function list(array $params = []): LengthAwarePaginator
    {
        return PayrollRun::query()
            ->filter($params)
            ->applySort($params['sort'] ?? null)
            ->paginate($this->perPage($params));
    }

    /**
     * @param  array{
     *     period_start?: string|null,
     *     period_end?: string|null,
     *     payroll_period_id?: int|null,
     *     currency?: string|null,
     *     notes?: string|null
     * }  $data
     *
     * @throws ValidationException
     */
    public function create(array $data): PayrollRun
    {
        $this->hrSettings->assertPayrollEnabled();

        if (empty($data['period_start'] ?? null) || empty($data['period_end'] ?? null)) {
            $window = $this->periodWindow();
            $data['period_start'] = $window['period_start'];
            $data['period_end'] = $window['period_end'];
        }

        if ($data['period_end'] < $data['period_start']) {
            throw ValidationException::withMessages([
                'period_end' => ['The payroll period end must be on or after the start date.'],
            ]);
        }

        $period = $this->findOrCreatePeriod((string) $data['period_start'], (string) $data['period_end']);
        $data['payroll_period_id'] = $period->id;

        $this->assertNoOverlappingRun($data['period_start'], $data['period_end']);

        $run = PayrollRun::query()->create([
            'payroll_period_id' => $data['payroll_period_id'] ?? null,
            'reference' => $this->nextReference(),
            'period_start' => $data['period_start'],
            'period_end' => $data['period_end'],
            'status' => PayrollRunStatus::Draft,
            'currency' => strtoupper((string) ($data['currency'] ?? $this->hrSettings->payrollCurrency())),
            'notes' => $data['notes'] ?? null,
        ]);

        return $this->generate($run);
    }

    public function show(PayrollRun $payrollRun): PayrollRun
    {
        return $payrollRun->load([
            'payrollPeriod',
            'items.employee.user',
            'items.lines',
            'processedByUser',
            'paidByUser',
        ]);
    }

    /**
     * Regenerate payslip items for a draft payroll run.
     *
     * @throws ValidationException
     */
    public function generate(PayrollRun $payrollRun): PayrollRun
    {
        $this->hrSettings->assertPayrollEnabled();
        $this->assertEditable($payrollRun);

        return DB::transaction(function () use ($payrollRun): PayrollRun {
            $payrollRun->items()->each(function (PayrollItem $item): void {
                $item->lines()->delete();
                $item->delete();
            });

            $employees = Employee::query()
                ->with('salary.components')
                ->whereIn('employment_status', [
                    EmploymentStatus::Active,
                    EmploymentStatus::OnLeave,
                ])
                ->whereHas('salary')
                ->get();

            $grossTotal = '0.00';
            $deductionTotal = '0.00';
            $netTotal = '0.00';

            foreach ($employees as $employee) {
                $item = $this->buildItem($payrollRun, $employee);
                $grossTotal = Money::add($grossTotal, $item->gross_pay);
                $deductionTotal = Money::add($deductionTotal, $item->deduction_total);
                $netTotal = Money::add($netTotal, $item->net_pay);
            }

            $payrollRun->fill([
                'gross_total' => $grossTotal,
                'deduction_total' => $deductionTotal,
                'net_total' => $netTotal,
                'employee_count' => $employees->count(),
            ]);
            $payrollRun->save();

            return $this->show($payrollRun);
        });
    }

    /**
     * Lock a draft payroll run for payment.
     *
     * @throws ValidationException
     */
    public function process(PayrollRun $payrollRun, User $actor): PayrollRun
    {
        $this->hrSettings->assertPayrollEnabled();
        $this->assertEditable($payrollRun);

        if ($payrollRun->items()->count() === 0) {
            throw ValidationException::withMessages([
                'items' => ['Payroll run has no payslips to process.'],
            ]);
        }

        $status = $this->hrSettings->payrollApprovalRequired()
            ? PayrollRunStatus::PendingApproval
            : PayrollRunStatus::Processed;

        $payrollRun->fill([
            'status' => $status,
            'processed_at' => now(),
            'processed_by' => $actor->id,
        ]);
        $payrollRun->save();

        $payrollRun = $this->show($payrollRun);

        event(new PayrollProcessed($payrollRun));

        if ($status === PayrollRunStatus::Processed) {
            $this->dispatchPayslips($payrollRun);
        }

        return $payrollRun;
    }

    /**
     * Approve a payroll run that is waiting for approval.
     *
     * @throws ValidationException
     */
    public function approve(PayrollRun $payrollRun, User $actor): PayrollRun
    {
        $this->hrSettings->assertPayrollEnabled();

        if ($payrollRun->status !== PayrollRunStatus::PendingApproval) {
            throw ValidationException::withMessages([
                'status' => ['Only payroll runs pending approval can be approved.'],
            ]);
        }

        $payrollRun->fill([
            'status' => PayrollRunStatus::Processed,
            'processed_at' => $payrollRun->processed_at ?? now(),
            'processed_by' => $payrollRun->processed_by ?? $actor->id,
        ]);
        $payrollRun->save();

        $payrollRun = $this->show($payrollRun);
        $this->dispatchPayslips($payrollRun);

        return $payrollRun;
    }

    /**
     * Mark a processed payroll run as paid and optionally post to accounting.
     *
     * @param  array{
     *     post_to_accounting?: bool|null,
     *     expense_account_id?: int|null,
     *     payable_account_id?: int|null
     * }  $options
     *
     * @throws ValidationException
     */
    public function pay(PayrollRun $payrollRun, User $actor, array $options = []): PayrollRun
    {
        $this->hrSettings->assertPayrollEnabled();

        if (! $payrollRun->status->canPay()) {
            throw ValidationException::withMessages([
                'status' => ['Only processed payroll runs can be marked as paid.'],
            ]);
        }

        return DB::transaction(function () use ($payrollRun, $actor, $options): PayrollRun {
            $payrollRun->fill([
                'status' => PayrollRunStatus::Paid,
                'paid_at' => now(),
                'paid_by' => $actor->id,
            ]);
            $payrollRun->save();

            if ($this->shouldPostToAccounting($options)) {
                $this->postToAccounting($payrollRun, $options);
            }

            $payrollRun = $this->show($payrollRun);
            event(new PayrollPaid($payrollRun));

            return $payrollRun;
        });
    }

    /**
     * Cancel a draft or processed payroll run.
     *
     * @throws ValidationException
     */
    public function cancel(PayrollRun $payrollRun): PayrollRun
    {
        $this->hrSettings->assertPayrollEnabled();

        if (! $payrollRun->status->canCancel()) {
            throw ValidationException::withMessages([
                'status' => ['This payroll run cannot be cancelled.'],
            ]);
        }

        $payrollRun->status = PayrollRunStatus::Cancelled;
        $payrollRun->save();

        return $this->show($payrollRun);
    }

    /**
     * @return LengthAwarePaginator<int, PayrollItem>
     */
    public function listForEmployee(Employee $employee, array $params = []): LengthAwarePaginator
    {
        return PayrollItem::query()
            ->with(['payrollRun', 'lines', 'employee.user'])
            ->where('employee_id', $employee->id)
            ->latest('id')
            ->paginate($this->perPage($params));
    }

    public function showItem(PayrollItem $item): PayrollItem
    {
        return $item->load([
            'employee.user',
            'lines',
            'payrollRun',
        ]);
    }

    /**
     * @throws ValidationException
     */
    protected function assertEditable(PayrollRun $payrollRun): void
    {
        if (! $payrollRun->status->isEditable()) {
            throw ValidationException::withMessages([
                'status' => ['Only draft payroll runs can be modified.'],
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    protected function assertNoOverlappingRun(string $periodStart, string $periodEnd, ?int $ignoreId = null): void
    {
        $overlaps = PayrollRun::query()
            ->whereNot('status', PayrollRunStatus::Cancelled)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->whereDate('period_start', '<=', $periodEnd)
            ->whereDate('period_end', '>=', $periodStart)
            ->exists();

        if ($overlaps) {
            throw ValidationException::withMessages([
                'period_start' => ['A payroll run already exists for an overlapping period.'],
            ]);
        }
    }

    protected function buildItem(PayrollRun $payrollRun, Employee $employee): PayrollItem
    {
        $salary = $employee->salary;
        $baseSalary = Money::add((string) $salary->base_salary, '0');
        $periodStart = $payrollRun->period_start->toDateString();
        $periodEnd = $payrollRun->period_end->toDateString();

        $workingDays = $this->countWorkingDays($periodStart, $periodEnd);
        $absentDays = $this->countAbsentDays($employee, $periodStart, $periodEnd);
        $unpaidLeaveDays = $this->countUnpaidLeaveDays($employee, $periodStart, $periodEnd);
        $overtimeMinutes = $this->countOvertimeMinutes($employee, $periodStart, $periodEnd);

        $dailyRate = $workingDays > 0
            ? Money::div($baseSalary, (string) $workingDays)
            : '0.00';
        $hourlyRate = Money::div($dailyRate, (string) $this->hrSettings->workingHoursPerDay());
        $overtimeHours = Money::div((string) $overtimeMinutes, '60');
        $overtimePay = $this->hrSettings->isOvertimeEnabled()
            ? Money::percent(Money::mul($hourlyRate, $overtimeHours), (string) $this->hrSettings->overtimeRatePercent())
            : '0.00';

        $absenceDeduction = Money::mul($dailyRate, (string) $absentDays);
        $unpaidLeaveDeduction = Money::mul($dailyRate, (string) $unpaidLeaveDays);
        $grossPay = Money::add($baseSalary, $overtimePay);
        $deductionTotal = Money::add($absenceDeduction, $unpaidLeaveDeduction);
        $componentLines = [];

        foreach ($salary->components ?? [] as $component) {
            if ($component->type !== PayrollLineType::Earning) {
                continue;
            }

            $componentAmount = $this->resolveComponentAmount($component, $baseSalary);
            $grossPay = Money::add($grossPay, $componentAmount);
            $componentLines[] = [$component, $componentAmount];
        }

        foreach ($salary->components ?? [] as $component) {
            if ($component->type === PayrollLineType::Earning) {
                continue;
            }

            $base = $component->is_tax ? $grossPay : $baseSalary;
            $componentAmount = $this->resolveComponentAmount($component, $base);
            $deductionTotal = Money::add($deductionTotal, $componentAmount);
            $componentLines[] = [$component, $componentAmount];
        }

        $netPay = Money::sub($grossPay, $deductionTotal);

        if (bccomp($netPay, '0', 2) < 0) {
            $netPay = '0.00';
            $deductionTotal = $grossPay;
        }

        $item = PayrollItem::query()->create([
            'payroll_run_id' => $payrollRun->id,
            'employee_id' => $employee->id,
            'base_salary' => $baseSalary,
            'gross_pay' => $grossPay,
            'deduction_total' => $deductionTotal,
            'net_pay' => $netPay,
            'working_days' => $workingDays,
            'absent_days' => $absentDays,
            'unpaid_leave_days' => $unpaidLeaveDays,
            'overtime_minutes' => $overtimeMinutes,
        ]);

        $this->createLine($item, PayrollLineType::Earning, 'base_salary', 'Base salary', $baseSalary);

        if (bccomp($overtimePay, '0', 2) > 0) {
            $this->createLine($item, PayrollLineType::Earning, 'overtime', 'Overtime', $overtimePay);
        }

        foreach ($componentLines as [$component, $componentAmount]) {
            if (bccomp($componentAmount, '0', 2) <= 0) {
                continue;
            }

            $this->createLine(
                $item,
                $component->type,
                $component->code,
                $component->label,
                $componentAmount,
            );
        }

        if (bccomp($absenceDeduction, '0', 2) > 0) {
            $this->createLine($item, PayrollLineType::Deduction, 'absence', 'Absence deduction', $absenceDeduction);
        }

        if (bccomp($unpaidLeaveDeduction, '0', 2) > 0) {
            $this->createLine($item, PayrollLineType::Deduction, 'unpaid_leave', 'Unpaid leave deduction', $unpaidLeaveDeduction);
        }

        return $item->load('lines');
    }

    protected function createLine(
        PayrollItem $item,
        PayrollLineType $type,
        string $code,
        string $label,
        string $amount,
    ): PayrollItemLine {
        return $item->lines()->create([
            'type' => $type,
            'code' => $code,
            'label' => $label,
            'amount' => $amount,
        ]);
    }

    protected function countWorkingDays(string $startDate, string $endDate): int
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->startOfDay();
        $count = 0;

        while ($start->lte($end)) {
            if ($this->hrSettings->isWorkingDate($start)) {
                $count++;
            }

            $start->addDay();
        }

        return $count;
    }

    protected function countAbsentDays(Employee $employee, string $periodStart, string $periodEnd): int
    {
        return Attendance::query()
            ->where('employee_id', $employee->id)
            ->where('status', AttendanceStatus::Absent)
            ->whereDate('work_date', '>=', $periodStart)
            ->whereDate('work_date', '<=', $periodEnd)
            ->get()
            ->filter(fn (Attendance $attendance): bool => $this->hrSettings->isWorkingDate($attendance->work_date))
            ->count();
    }

    protected function countUnpaidLeaveDays(Employee $employee, string $periodStart, string $periodEnd): int
    {
        $this->leaveTypeService->ensureDefaults();

        $unpaidCodes = LeaveType::query()
            ->where('is_paid', false)
            ->pluck('code');

        if ($unpaidCodes->isEmpty()) {
            $unpaidCodes = collect(['unpaid']);
        }

        $requests = LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->where('status', LeaveStatus::Approved)
            ->whereIn('type', $unpaidCodes->all())
            ->whereDate('start_date', '<=', $periodEnd)
            ->whereDate('end_date', '>=', $periodStart)
            ->get();

        $days = 0;

        foreach ($requests as $request) {
            $start = Carbon::parse(max($request->start_date->toDateString(), $periodStart));
            $end = Carbon::parse(min($request->end_date->toDateString(), $periodEnd));

            while ($start->lte($end)) {
                if ($this->hrSettings->isWorkingDate($start)) {
                    $days++;
                }

                $start->addDay();
            }
        }

        return $days;
    }

    protected function countOvertimeMinutes(Employee $employee, string $periodStart, string $periodEnd): int
    {
        if (! $this->hrSettings->isOvertimeEnabled()) {
            return 0;
        }

        return (int) Attendance::query()
            ->where('employee_id', $employee->id)
            ->whereDate('work_date', '>=', $periodStart)
            ->whereDate('work_date', '<=', $periodEnd)
            ->sum('overtime_minutes');
    }

    protected function resolveComponentAmount(EmployeeSalaryComponent $component, string $baseSalary): string
    {
        if ($component->calculation === SalaryComponentCalculation::Percent) {
            return Money::percent($baseSalary, (string) $component->amount);
        }

        return Money::add((string) $component->amount, '0');
    }

    /**
     * @param  array{
     *     post_to_accounting?: bool|null,
     *     expense_account_id?: int|null,
     *     payable_account_id?: int|null
     * }  $options
     */
    protected function shouldPostToAccounting(array $options): bool
    {
        if (array_key_exists('post_to_accounting', $options)) {
            return (bool) $options['post_to_accounting'];
        }

        return $this->hrSettings->payrollExpenseAccountId() !== null
            && $this->hrSettings->payrollPayableAccountId() !== null;
    }

    /**
     * @throws ValidationException
     */
    public function createFromCurrentPeriod(?string $currency = null, ?string $notes = null): PayrollRun
    {
        $period = $this->ensureCurrentPeriod();

        return $this->create([
            'payroll_period_id' => $period->id,
            'period_start' => $period->period_start->toDateString(),
            'period_end' => $period->period_end->toDateString(),
            'currency' => $currency,
            'notes' => $notes,
        ]);
    }

    public function ensureCurrentPeriod(?Carbon $asOf = null): PayrollPeriod
    {
        $window = $this->periodWindow($asOf ?? now());

        return $this->findOrCreatePeriod($window['period_start'], $window['period_end'], $window['payment_date']);
    }

    /**
     * Create a draft run for the current period when today is the configured payment day.
     */
    public function scheduleCurrentPeriodRun(): ?PayrollRun
    {
        if (! $this->hrSettings->isPayrollEnabled()) {
            return null;
        }

        $period = $this->ensureCurrentPeriod();

        if (! $period->payment_date->isToday()) {
            return null;
        }

        $existing = PayrollRun::query()
            ->where(function ($query) use ($period): void {
                $query->where('payroll_period_id', $period->id)
                    ->orWhere(function ($query) use ($period): void {
                        $query->whereDate('period_start', $period->period_start->toDateString())
                            ->whereDate('period_end', $period->period_end->toDateString());
                    });
            })
            ->whereNot('status', PayrollRunStatus::Cancelled)
            ->exists();

        if ($existing) {
            return null;
        }

        return $this->create([
            'payroll_period_id' => $period->id,
            'period_start' => $period->period_start->toDateString(),
            'period_end' => $period->period_end->toDateString(),
        ]);
    }

    /**
     * @return array{period_start: string, period_end: string, payment_date: string}
     */
    public function periodWindow(?Carbon $asOf = null): array
    {
        $asOf ??= now();
        $frequency = $this->hrSettings->payrollFrequency();

        [$start, $end] = match ($frequency) {
            PayFrequency::Weekly => [
                $asOf->copy()->startOfWeek(),
                $asOf->copy()->endOfWeek(),
            ],
            PayFrequency::Biweekly => $this->biweeklyBounds($asOf),
            default => [
                $asOf->copy()->startOfMonth(),
                $asOf->copy()->endOfMonth(),
            ],
        };

        $paymentDay = $this->hrSettings->payrollPaymentDay();
        $paymentDate = $frequency === PayFrequency::Monthly
            ? $start->copy()->day(min($paymentDay, $end->daysInMonth))
            : $end->copy();

        return [
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
            'payment_date' => $paymentDate->toDateString(),
        ];
    }

    protected function findOrCreatePeriod(string $periodStart, string $periodEnd, ?string $paymentDate = null): PayrollPeriod
    {
        $periodStart = Carbon::parse($periodStart)->toDateString();
        $periodEnd = Carbon::parse($periodEnd)->toDateString();

        $existing = PayrollPeriod::query()
            ->whereDate('period_start', $periodStart)
            ->whereDate('period_end', $periodEnd)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $window = $this->periodWindow(Carbon::parse($periodStart));

        try {
            return PayrollPeriod::query()->create([
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'payment_date' => $paymentDate ?? $window['payment_date'],
                'frequency' => $this->hrSettings->payrollFrequency(),
                'status' => PayrollPeriodStatus::Open,
            ]);
        } catch (QueryException) {
            $created = PayrollPeriod::query()
                ->whereDate('period_start', $periodStart)
                ->whereDate('period_end', $periodEnd)
                ->first();

            if ($created !== null) {
                return $created;
            }

            throw ValidationException::withMessages([
                'period_start' => ['Unable to create a payroll period for this window.'],
            ]);
        }
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function biweeklyBounds(Carbon $asOf): array
    {
        $yearStart = $asOf->copy()->startOfYear();
        $days = (int) $yearStart->diffInDays($asOf);
        $bucket = intdiv($days, 14);
        $start = $yearStart->copy()->addDays($bucket * 14);

        return [$start, $start->copy()->addDays(13)];
    }

    /**
     * @param  array{
     *     expense_account_id?: int|null,
     *     payable_account_id?: int|null
     * }  $options
     *
     * @throws ValidationException
     */
    protected function postToAccounting(PayrollRun $payrollRun, array $options): void
    {
        $expenseAccountId = (int) ($options['expense_account_id'] ?? $this->hrSettings->payrollExpenseAccountId() ?? 0);
        $payableAccountId = (int) ($options['payable_account_id'] ?? $this->hrSettings->payrollPayableAccountId() ?? 0);

        if ($expenseAccountId <= 0 || $payableAccountId <= 0) {
            throw ValidationException::withMessages([
                'expense_account_id' => ['Expense and payable accounts are required when posting to accounting.'],
            ]);
        }

        Account::query()->whereKey($expenseAccountId)->where('is_active', true)->firstOrFail();
        Account::query()->whereKey($payableAccountId)->where('is_active', true)->firstOrFail();

        $amount = Money::add((string) $payrollRun->net_total, '0');

        if (bccomp($amount, '0', 2) <= 0) {
            return;
        }

        $this->journalEntryService->postUnique(
            $payrollRun,
            'payroll',
            fn (JournalEntryService $service): JournalEntry => $service->createDraft(
                reference: $payrollRun->reference,
                description: 'Payroll '.$payrollRun->reference.' ('.$payrollRun->period_start->toDateString().' to '.$payrollRun->period_end->toDateString().')',
                entryDate: $payrollRun->period_end->toDateString(),
                lines: [
                    [
                        'account_id' => $expenseAccountId,
                        'debit' => $amount,
                        'credit' => '0.00',
                        'description' => 'Payroll expense',
                    ],
                    [
                        'account_id' => $payableAccountId,
                        'debit' => '0.00',
                        'credit' => $amount,
                        'description' => 'Payroll payable',
                    ],
                ],
                source: $payrollRun,
                entryType: 'payroll',
            ),
        );
    }

    protected function nextReference(): string
    {
        $prefix = 'PAY-'.now()->format('Ym').'-';
        $latest = PayrollRun::query()
            ->where('reference', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('reference');

        $sequence = 1;

        if (is_string($latest) && preg_match('/-(\d+)$/', $latest, $matches) === 1) {
            $sequence = ((int) $matches[1]) + 1;
        }

        return $prefix.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
    }

    protected function dispatchPayslips(PayrollRun $payrollRun): void
    {
        $payrollRun->loadMissing('items');

        foreach ($payrollRun->items as $item) {
            event(new PayslipAvailable($item));
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
