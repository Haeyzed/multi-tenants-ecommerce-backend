<?php

declare(strict_types=1);

namespace App\Services\Tenant\HR;

use App\Enums\Tenant\HR\PayFrequency;
use App\Models\Tenant\HR\TaxTable;
use App\Models\Tenant\HR\TaxTableBand;
use App\Support\Money;

/**
 * Progressive PAYE from a country tax table, annualized to the pay frequency.
 */
class PayeCalculatorService
{
    /**
     * Active table.
     *
     * @param  ?int  $tableId
     * @return ?TaxTable
     */
    public function activeTable(?int $tableId = null): ?TaxTable
    {
        if ($tableId !== null && $tableId > 0) {
            return TaxTable::query()->with('bands')->where('is_active', true)->find($tableId);
        }

        return TaxTable::query()
            ->with('bands')
            ->where('is_active', true)
            ->orderByDesc('year')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Tax due for one payroll period from period gross pay.
     *
     * @param  string  $periodGross
     * @param  PayFrequency  $frequency
     * @param  TaxTable  $table
     * @return string
     */
    public function periodTax(string $periodGross, PayFrequency $frequency, TaxTable $table): string
    {
        $periods = $this->periodsPerYear($frequency);
        $annualGross = Money::mul($periodGross, (string) $periods);
        $annualTax = $this->annualTax($annualGross, $table);

        return Money::div($annualTax, (string) $periods);
    }

    /**
     * Cumulative PAYE: tax-to-date on year-to-date income minus tax already withheld.
     *
     * @param  string  $periodGross
     * @param  string  $priorYtdGross
     * @param  string  $priorYtdPaye
     * @param  PayFrequency  $frequency
     * @param  TaxTable  $table
     * @param  int  $periodsElapsed
     * @return string
     */
    public function cumulativePeriodTax(
        string $periodGross,
        string $priorYtdGross,
        string $priorYtdPaye,
        PayFrequency $frequency,
        TaxTable $table,
        int $periodsElapsed,
    ): string {
        $elapsed = max(1, $periodsElapsed);
        $periods = $this->periodsPerYear($frequency);
        $ytdGross = Money::add($priorYtdGross, $periodGross);
        $projectedAnnual = bcdiv(bcmul($ytdGross, (string) $periods, 4), (string) $elapsed, 2);
        $taxToDate = bcdiv(bcmul($this->annualTax($projectedAnnual, $table), (string) $elapsed, 4), (string) $periods, 2);
        $due = Money::sub($taxToDate, $priorYtdPaye);

        return bccomp($due, '0', 2) < 0 ? '0.00' : $due;
    }

    /**
     * Annual tax.
     *
     * @param  string  $annualGross
     * @param  TaxTable  $table
     * @return string
     */
    public function annualTax(string $annualGross, TaxTable $table): string
    {
        $percentRelief = Money::percent($annualGross, (string) $table->relief_percent);
        $minimumFixed = Money::percent($annualGross, (string) $table->relief_minimum_percent);
        $fixedRelief = bccomp($minimumFixed, (string) $table->relief_fixed, 2) > 0
            ? $minimumFixed
            : Money::add((string) $table->relief_fixed, '0');
        $taxable = Money::sub($annualGross, $percentRelief);
        $taxable = Money::sub($taxable, $fixedRelief);
        $taxable = Money::sub($taxable, (string) $table->personal_allowance);

        if (bccomp($taxable, '0', 2) <= 0) {
            return '0.00';
        }

        $bands = $table->relationLoaded('bands')
            ? $table->bands
            : $table->bands()->get();

        $tax = '0.00';

        foreach ($bands as $band) {

            $slice = $this->bandSlice($band, $taxable);

            if (bccomp($slice, '0', 2) <= 0) {
                continue;
            }

            $tax = Money::add($tax, Money::percent($slice, (string) $band->rate_percent));
        }

        return $tax;
    }

    /**
     * Band slice.
     *
     * @param  TaxTableBand  $band
     * @param  string  $taxable
     * @return string
     */
    protected function bandSlice(TaxTableBand $band, string $taxable): string
    {
        $min = Money::add((string) $band->min_amount, '0');

        if (bccomp($taxable, $min, 2) <= 0) {
            return '0.00';
        }

        $max = $band->max_amount === null
            ? $taxable
            : Money::add((string) $band->max_amount, '0');

        $upper = bccomp($taxable, $max, 2) < 0 ? $taxable : $max;

        return Money::sub($upper, $min);
    }

    /**
     * Periods per year.
     *
     * @param  PayFrequency  $frequency
     * @return int
     */
    protected function periodsPerYear(PayFrequency $frequency): int
    {
        return match ($frequency) {
            PayFrequency::Weekly => 52,
            PayFrequency::Biweekly => 26,
            default => 12,
        };
    }
}
