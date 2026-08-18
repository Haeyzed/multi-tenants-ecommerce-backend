<?php

declare(strict_types=1);

namespace App\Services\Tenant\HR;

use App\Enums\Tenant\HR\PayFrequency;
use App\Models\Tenant\TaxTable;
use App\Models\Tenant\TaxTableBand;
use App\Support\Money;

/**
 * Progressive PAYE from a country tax table, annualized to the pay frequency.
 */
class PayeCalculatorService
{
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
     */
    public function periodTax(string $periodGross, PayFrequency $frequency, TaxTable $table): string
    {
        $periods = $this->periodsPerYear($frequency);
        $annualGross = Money::mul($periodGross, (string) $periods);
        $annualTax = $this->annualTax($annualGross, $table);

        return Money::div($annualTax, (string) $periods);
    }

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

    protected function periodsPerYear(PayFrequency $frequency): int
    {
        return match ($frequency) {
            PayFrequency::Weekly => 52,
            PayFrequency::Biweekly => 26,
            default => 12,
        };
    }
}
