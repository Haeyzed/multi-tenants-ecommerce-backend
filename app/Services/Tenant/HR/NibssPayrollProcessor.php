<?php

declare(strict_types=1);

namespace App\Services\Tenant\HR;

use App\Enums\Tenant\HR\PayrollRunStatus;
use App\Models\HR\PayrollItem;
use App\Models\HR\PayrollRun;
use App\Support\Money;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * NIBSS bulk credit processor for paid payroll runs.
 */
class NibssPayrollProcessor
{
    /**
     * Create a new class instance.
     *
     * @param  HrSettingsService  $hrSettings
     * @param  HrCsvExporter  $csv
     */
    public function __construct(
        private readonly HrSettingsService $hrSettings,
        private readonly HrCsvExporter $csv,
    ) {}

    /**
     * Instructions.
     *
     * @param  PayrollRun  $payrollRun
     * @return list<array<string, scalar|null>>
     */
    public function instructions(PayrollRun $payrollRun): array
    {
        $payrollRun->loadMissing(['items.employee.user']);

        return $payrollRun->items->map(function (PayrollItem $item) use ($payrollRun): array {
            $name = trim(($item->employee?->user?->first_name ?? '').' '.($item->employee?->user?->last_name ?? ''));

            return [
                'employee_id' => $item->employee_id,
                'employee_number' => $item->employee?->employee_number,
                'name' => $name !== '' ? $name : ($item->account_name ?: null),
                'bank_name' => $item->bank_name,
                'bank_code' => $item->bank_code,
                'account_number' => $item->account_number,
                'account_name' => $item->account_name,
                'net_pay' => $item->net_pay,
                'currency' => $payrollRun->currency,
            ];
        })->values()->all();
    }

    /**
     * Download.
     *
     * @param  PayrollRun  $payrollRun
     * @return StreamedResponse
     */
    public function download(PayrollRun $payrollRun): StreamedResponse
    {
        return $this->csv->rows(
            'nibss-'.$payrollRun->reference.'.csv',
            $this->credits($payrollRun),
        );
    }

    /**
     * Submit NIP bulk credits to the configured NIBSS-compatible endpoint.
     *
     * @param  PayrollRun  $payrollRun
     * @return PayrollRun
     *
     * @throws ValidationException
     */
    public function submit(PayrollRun $payrollRun): PayrollRun
    {
        if ($payrollRun->status !== PayrollRunStatus::Paid) {
            throw ValidationException::withMessages([
                'status' => ['Only paid payroll runs can be sent to NIBSS.'],
            ]);
        }

        $this->assertConfigured();

        $credits = $this->credits($payrollRun);

        if ($credits === []) {
            throw ValidationException::withMessages([
                'items' => ['No banked payslips are available to disburse.'],
            ]);
        }

        $payload = [
            'institution_code' => $this->hrSettings->nibssInstitutionCode(),
            'originator_account' => $this->hrSettings->nibssOriginatorAccount(),
            'originator_bank_code' => $this->hrSettings->nibssOriginatorBankCode(),
            'reference' => $payrollRun->reference,
            'currency' => $payrollRun->currency,
            'credits' => $credits,
        ];

        try {
            $response = Http::timeout(30)
                ->acceptJson()
                ->withToken((string) $this->hrSettings->nibssApiKey())
                ->post(rtrim((string) $this->hrSettings->nibssBaseUrl(), '/').'/credits', $payload)
                ->throw()
                ->json();
        } catch (RequestException $exception) {
            $payrollRun->fill([
                'nibss_status' => 'failed',
                'nibss_submitted_at' => now(),
            ]);
            $payrollRun->save();

            throw ValidationException::withMessages([
                'nibss' => ['NIBSS disbursement failed: '.$exception->getMessage()],
            ]);
        }

        $reference = is_array($response)
            ? (string) ($response['reference'] ?? $response['session_id'] ?? $payrollRun->reference)
            : $payrollRun->reference;

        $payrollRun->fill([
            'nibss_reference' => $reference,
            'nibss_status' => 'submitted',
            'nibss_submitted_at' => now(),
        ]);
        $payrollRun->save();

        return $payrollRun->fresh(['items.employee.user']) ?? $payrollRun;
    }

    /**
     * Credits.
     *
     * @param  PayrollRun  $payrollRun
     * @return list<array<string, scalar|null>>
     */
    protected function credits(PayrollRun $payrollRun): array
    {
        return array_values(array_filter(
            $this->instructions($payrollRun),
            function (array $row): bool {
                return ($row['account_number'] ?? '') !== ''
                    && ($row['bank_code'] ?? '') !== ''
                    && bccomp(Money::add((string) ($row['net_pay'] ?? '0'), '0'), '0', 2) > 0;
            },
        ));
    }

    /**
     * Assert configured.
     *
     * @return void
     *
     * @throws ValidationException
     */
    protected function assertConfigured(): void
    {
        if (! $this->hrSettings->isNibssEnabled()) {
            throw ValidationException::withMessages([
                'nibss' => ['NIBSS payroll disbursement is disabled in HR settings.'],
            ]);
        }

        if (
            $this->hrSettings->nibssBaseUrl() === null
            || $this->hrSettings->nibssApiKey() === null
            || $this->hrSettings->nibssOriginatorAccount() === null
            || $this->hrSettings->nibssOriginatorBankCode() === null
        ) {
            throw ValidationException::withMessages([
                'nibss' => ['NIBSS base URL, API key, originator account, and bank code are required.'],
            ]);
        }
    }
}
