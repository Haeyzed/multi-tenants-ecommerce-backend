<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Landlord\Tenant;
use App\Services\Tenant\HR\HrSettingsService;
use App\Services\Tenant\HR\PayrollRunService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Ensure the current payroll period exists and create a draft run on payment day.
 */
class EnsureCurrentPayrollPeriodJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public ?string $tenantId = null) {}

    public function handle(HrSettingsService $hrSettings, PayrollRunService $payrollRuns): void
    {
        if ($this->tenantId === null || $this->tenantId === '') {
            Log::warning('EnsureCurrentPayrollPeriodJob: tenant id is required');

            return;
        }

        $tenant = Tenant::query()->find($this->tenantId);

        if ($tenant === null) {
            Log::warning('EnsureCurrentPayrollPeriodJob: tenant not found', ['tenant_id' => $this->tenantId]);

            return;
        }

        $tenant->run(function () use ($hrSettings, $payrollRuns): void {
            if (! $hrSettings->isPayrollEnabled()) {
                return;
            }

            $payrollRuns->ensureCurrentPeriod();
            $payrollRuns->scheduleCurrentPeriodRun();
        });
    }
}
