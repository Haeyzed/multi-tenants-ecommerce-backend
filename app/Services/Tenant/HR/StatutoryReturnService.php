<?php

declare(strict_types=1);

namespace App\Services\Tenant\HR;

use App\Enums\Tenant\HR\PayrollRunStatus;
use App\Enums\Tenant\HR\StatutoryReturnKind;
use App\Models\HR\PayrollItem;
use App\Models\HR\PayrollItemLine;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * PAYE, pension, NHF, and NSITF filing schedules from posted payroll.
 */
class StatutoryReturnService
{
    /**
     * @param  array{from?: string|null, to?: string|null, kind?: string|null}  $params
     * @return array<string, mixed>
     */
    public function generate(array $params = []): array
    {
        $kind = StatutoryReturnKind::tryFrom((string) ($params['kind'] ?? 'combined')) ?? StatutoryReturnKind::Combined;
        $from = $params['from'] ?? now()->startOfMonth()->toDateString();
        $to = $params['to'] ?? now()->endOfMonth()->toDateString();

        $items = PayrollItem::query()
            ->with(['employee.user', 'employee.department', 'lines', 'payrollRun'])
            ->whereHas('payrollRun', function ($query) use ($from, $to): void {
                $query->whereIn('status', [
                    PayrollRunStatus::Processed->value,
                    PayrollRunStatus::Paid->value,
                ])
                    ->whereDate('period_end', '>=', $from)
                    ->whereDate('period_start', '<=', $to);
            })
            ->orderBy('id')
            ->get();

        $rows = $items->map(fn (PayrollItem $item): array => $this->row($item))->all();

        return match ($kind) {
            StatutoryReturnKind::Paye => $this->schedule($from, $to, $kind, $rows, ['paye'], ['gross', 'paye']),
            StatutoryReturnKind::Pension => $this->schedule($from, $to, $kind, $rows, ['pension', 'employer_pension'], ['pension', 'employer_pension']),
            StatutoryReturnKind::Nhf => $this->schedule($from, $to, $kind, $rows, ['nhf'], ['nhf']),
            StatutoryReturnKind::Nsitf => $this->schedule($from, $to, $kind, $rows, ['employer_nsitf'], ['employer_nsitf']),
            StatutoryReturnKind::Combined => [
                'from' => $from,
                'to' => $to,
                'kind' => $kind->value,
                'totals' => $this->totals($rows, ['gross', 'paye', 'pension', 'employer_pension', 'nhf', 'employer_nsitf']),
                'rows' => $rows,
            ],
        };
    }

    /**
     * @param  array{from?: string|null, to?: string|null, kind?: string|null}  $params
     *
     * @throws ValidationException
     */
    public function generateOrFail(array $params = []): array
    {
        if (isset($params['kind']) && StatutoryReturnKind::tryFrom((string) $params['kind']) === null) {
            throw ValidationException::withMessages([
                'kind' => ['Statutory return kind is invalid.'],
            ]);
        }

        return $this->generate($params);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $required
     * @param  list<string>  $totalKeys
     * @return array<string, mixed>
     */
    protected function schedule(string $from, string $to, StatutoryReturnKind $kind, array $rows, array $required, array $totalKeys): array
    {
        $filtered = array_values(array_filter(
            $rows,
            function (array $row) use ($required): bool {
                foreach ($required as $key) {
                    if (bccomp(Money::add((string) ($row[$key] ?? '0'), '0'), '0', 2) > 0) {
                        return true;
                    }
                }

                return false;
            },
        ));

        return [
            'from' => $from,
            'to' => $to,
            'kind' => $kind->value,
            'totals' => $this->totals($filtered, $totalKeys),
            'rows' => $filtered,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function row(PayrollItem $item): array
    {
        $name = trim(($item->employee?->user?->first_name ?? '').' '.($item->employee?->user?->last_name ?? ''));

        return [
            'employee_id' => $item->employee_id,
            'employee_number' => $item->employee?->employee_number,
            'name' => $name,
            'tax_id' => $item->employee?->tax_id,
            'pension_pin' => $item->employee?->pension_pin,
            'nhf_number' => $item->employee?->nhf_number,
            'nsitf_number' => $item->employee?->nsitf_number,
            'period_end' => $item->payrollRun?->period_end instanceof Carbon
                ? $item->payrollRun->period_end->toDateString()
                : null,
            'reference' => $item->payrollRun?->reference,
            'gross' => $item->gross_pay,
            'paye' => $this->lineAmount($item, 'paye'),
            'pension' => $this->lineAmount($item, 'pension'),
            'nhf' => $this->lineAmount($item, 'nhf'),
            'employer_pension' => $item->employer_pension,
            'employer_nsitf' => $item->employer_nsitf,
            'ytd_gross' => $item->ytd_gross,
            'ytd_paye' => $item->ytd_paye,
            'net_pay' => $item->net_pay,
        ];
    }

    protected function lineAmount(PayrollItem $item, string $code): string
    {
        $line = $item->lines->first(fn (PayrollItemLine $line): bool => $line->code === $code);

        return $line === null ? '0.00' : Money::add((string) $line->amount, '0');
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $keys
     * @return array<string, string>
     */
    protected function totals(array $rows, array $keys): array
    {
        $totals = [];

        foreach ($keys as $key) {
            $totals[$key] = '0.00';

            foreach ($rows as $row) {
                $totals[$key] = Money::add($totals[$key], (string) ($row[$key] ?? '0'));
            }
        }

        return $totals;
    }
}
