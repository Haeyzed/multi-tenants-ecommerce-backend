<?php

declare(strict_types=1);

namespace App\Services\Tenant\HR;

use App\Enums\Tenant\HR\PayFrequency;
use App\Enums\Tenant\HR\PayrollPeriodStatus;
use App\Models\Tenant\HR\PayrollPeriod;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Payroll period windows and persistence for the current tenant.
 */
class PayrollPeriodService
{
    /**
     * Create a new class instance.
     *
     * @param  HrSettingsService  $hrSettings
     */
    public function __construct(private readonly HrSettingsService $hrSettings) {}

    /**
     * Ensure current period.
     *
     * @param  ?Carbon  $asOf
     * @return PayrollPeriod
     */
    public function ensureCurrentPeriod(?Carbon $asOf = null): PayrollPeriod
    {
        $window = $this->periodWindow($asOf ?? now());

        return $this->findOrCreatePeriod($window['period_start'], $window['period_end'], $window['payment_date']);
    }

    /**
     * Period window.
     *
     * @param  ?Carbon  $asOf
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

    /**
     * Find or create period.
     *
     * @param  string  $periodStart
     * @param  string  $periodEnd
     * @param  ?string  $paymentDate
     * @return PayrollPeriod
     */
    public function findOrCreatePeriod(string $periodStart, string $periodEnd, ?string $paymentDate = null): PayrollPeriod
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
     * Biweekly bounds.
     *
     * @param  Carbon  $asOf
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
}
