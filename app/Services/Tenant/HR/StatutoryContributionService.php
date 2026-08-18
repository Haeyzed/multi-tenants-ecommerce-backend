<?php

declare(strict_types=1);

namespace App\Services\Tenant\HR;

use App\Support\Money;

/**
 * Nigeria statutory contributions: pension, NHF, and NSITF.
 */
class StatutoryContributionService
{
    public function __construct(private readonly HrSettingsService $hrSettings) {}

    /**
     * @return array{
     *     pension: string,
     *     employer_pension: string,
     *     nhf: string,
     *     employer_nsitf: string
     * }
     */
    public function forPayslip(string $basicSalary, string $grossPay): array
    {
        $pension = '0.00';
        $employerPension = '0.00';
        $nhf = '0.00';
        $nsitf = '0.00';

        if ($this->hrSettings->isPensionEnabled()) {
            $pension = Money::percent($basicSalary, $this->hrSettings->pensionEmployeePercent());
            $employerPension = Money::percent($basicSalary, $this->hrSettings->pensionEmployerPercent());
        }

        if ($this->hrSettings->isNhfEnabled()) {
            $nhf = Money::percent($basicSalary, $this->hrSettings->nhfPercent());
        }

        if ($this->hrSettings->isNsitfEnabled()) {
            $nsitf = Money::percent($grossPay, $this->hrSettings->nsitfPercent());
        }

        return [
            'pension' => $pension,
            'employer_pension' => $employerPension,
            'nhf' => $nhf,
            'employer_nsitf' => $nsitf,
        ];
    }
}
