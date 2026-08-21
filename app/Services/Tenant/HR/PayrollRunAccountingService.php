<?php

declare(strict_types=1);

namespace App\Services\Tenant\HR;

use App\Models\HR\PayrollRun;
use App\Models\Tenant\Account;
use App\Models\Tenant\JournalEntry;
use App\Services\Tenant\Accounting\JournalEntryService;
use App\Support\Money;
use Illuminate\Validation\ValidationException;

/**
 * Posts payroll runs to the accounting ledger.
 */
class PayrollRunAccountingService
{
    /**
     * Create a new class instance.
     *
     * @param  JournalEntryService  $journalEntryService
     * @param  HrSettingsService  $hrSettings
     */
    public function __construct(
        private readonly JournalEntryService $journalEntryService,
        private readonly HrSettingsService $hrSettings,
    ) {}

    /**
     * post_to_accounting?: bool|null, expense_account_id?: int|null, payable_account_id?: int|null, tax_payable_account_id?: int|null, deduction_payable_account_id?: int|null }  $options
     *
     * @param  array{
     *     post_to_accounting?: bool|null,
     *     expense_account_id?: int|null,
     *     payable_account_id?: int|null,
     *     tax_payable_account_id?: int|null,
     *     deduction_payable_account_id?: int|null
     * }  $options
     * @return bool
     */
    public function shouldPost(array $options): bool
    {
        if (array_key_exists('post_to_accounting', $options)) {
            return (bool) $options['post_to_accounting'];
        }

        return $this->hrSettings->payrollExpenseAccountId() !== null
            && $this->hrSettings->payrollPayableAccountId() !== null;
    }

    /**
     * expense_account_id?: int|null, payable_account_id?: int|null, tax_payable_account_id?: int|null, deduction_payable_account_id?: int|null }  $options
     *
     * @param  PayrollRun  $payrollRun
     * @param  array{
     *     expense_account_id?: int|null,
     *     payable_account_id?: int|null,
     *     tax_payable_account_id?: int|null,
     *     deduction_payable_account_id?: int|null
     * }  $options
     * @return void
     *
     * @throws ValidationException
     */
    public function post(PayrollRun $payrollRun, array $options): void
    {
        $expenseAccountId = (int) ($options['expense_account_id'] ?? $this->hrSettings->payrollExpenseAccountId() ?? 0);
        $payableAccountId = (int) ($options['payable_account_id'] ?? $this->hrSettings->payrollPayableAccountId() ?? 0);
        $taxPayableAccountId = (int) ($options['tax_payable_account_id'] ?? $this->hrSettings->payrollTaxPayableAccountId() ?? 0);
        $deductionPayableAccountId = (int) ($options['deduction_payable_account_id'] ?? $this->hrSettings->payrollDeductionPayableAccountId() ?? 0);

        if ($expenseAccountId <= 0 || $payableAccountId <= 0) {
            throw ValidationException::withMessages([
                'expense_account_id' => ['Expense and payable accounts are required when posting to accounting.'],
            ]);
        }

        Account::query()->whereKey($expenseAccountId)->where('is_active', true)->firstOrFail();
        Account::query()->whereKey($payableAccountId)->where('is_active', true)->firstOrFail();

        $gross = Money::add((string) $payrollRun->gross_total, '0');

        if (bccomp($gross, '0', 2) <= 0) {
            return;
        }

        $payrollRun->loadMissing('items.lines');
        $paye = '0.00';

        foreach ($payrollRun->items as $item) {
            foreach ($item->lines as $line) {
                if ($line->code === 'paye') {
                    $paye = Money::add($paye, (string) $line->amount);
                }
            }
        }

        $otherDeductions = Money::sub((string) $payrollRun->deduction_total, $paye);

        if (bccomp($otherDeductions, '0', 2) < 0) {
            $otherDeductions = '0.00';
        }

        $net = Money::add((string) $payrollRun->net_total, '0');
        $lines = [
            [
                'account_id' => $expenseAccountId,
                'debit' => $gross,
                'credit' => '0.00',
                'description' => 'Payroll expense',
            ],
        ];

        if (bccomp($paye, '0', 2) > 0) {
            if ($taxPayableAccountId <= 0) {
                throw ValidationException::withMessages([
                    'tax_payable_account_id' => ['A tax payable account is required when PAYE is withheld.'],
                ]);
            }

            Account::query()->whereKey($taxPayableAccountId)->where('is_active', true)->firstOrFail();
            $lines[] = [
                'account_id' => $taxPayableAccountId,
                'debit' => '0.00',
                'credit' => $paye,
                'description' => 'PAYE payable',
            ];
        }

        $netCredit = $net;

        if (bccomp($otherDeductions, '0', 2) > 0) {
            if ($deductionPayableAccountId > 0) {
                Account::query()->whereKey($deductionPayableAccountId)->where('is_active', true)->firstOrFail();
                $lines[] = [
                    'account_id' => $deductionPayableAccountId,
                    'debit' => '0.00',
                    'credit' => $otherDeductions,
                    'description' => 'Payroll deductions payable',
                ];
            } else {
                $netCredit = Money::add($netCredit, $otherDeductions);
            }
        }

        if (bccomp($netCredit, '0', 2) > 0) {
            $lines[] = [
                'account_id' => $payableAccountId,
                'debit' => '0.00',
                'credit' => $netCredit,
                'description' => 'Payroll payable',
            ];
        }

        $this->journalEntryService->postUnique(
            $payrollRun,
            'payroll',
            fn (JournalEntryService $service): JournalEntry => $service->createDraft(
                reference: $payrollRun->reference,
                description: 'Payroll '.$payrollRun->reference.' ('.$payrollRun->period_start->toDateString().' to '.$payrollRun->period_end->toDateString().')',
                entryDate: $payrollRun->period_end->toDateString(),
                lines: $lines,
                source: $payrollRun,
                entryType: 'payroll',
            ),
        );
    }
}
