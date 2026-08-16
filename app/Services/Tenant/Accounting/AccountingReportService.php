<?php

declare(strict_types=1);

namespace App\Services\Tenant\Accounting;

use App\Enums\Tenant\Accounting\JournalEntryStatus;
use App\Models\Tenant\Account;
use App\Models\Tenant\JournalEntryLine;
use App\Support\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Trial balance, ledger, and account balance reporting over posted journals.
 */
class AccountingReportService
{
    /**
     * Active-account trial balance from posted journal lines.
     *
     * @return list<array{code: string, name: string, type: string, debit: string, credit: string, balance: string}>
     */
    public function trialBalance(?string $asOfDate = null): array
    {
        $accounts = Account::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        $rows = [];

        foreach ($accounts as $account) {
            $totals = $this->aggregateAccount($account, $asOfDate);
            $rows[] = [
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type->value,
                'debit' => $totals['debit_total'],
                'credit' => $totals['credit_total'],
                'balance' => $totals['balance'],
            ];
        }

        return $rows;
    }

    /**
     * Posted ledger lines for an account with optional date filters.
     *
     * @param  array{date_from?: string|null, date_to?: string|null, per_page?: int|null}  $params
     * @return LengthAwarePaginator<int, JournalEntryLine>
     */
    public function ledger(Account $account, array $params = []): LengthAwarePaginator
    {
        $query = JournalEntryLine::query()
            ->select('journal_entry_lines.*')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->where('journal_entry_lines.account_id', $account->id)
            ->where('journal_entries.status', JournalEntryStatus::Posted->value)
            ->when(! empty($params['date_from']), function (Builder $q) use ($params): void {
                $q->whereDate('journal_entries.entry_date', '>=', (string) $params['date_from']);
            })
            ->when(! empty($params['date_to']), function (Builder $q) use ($params): void {
                $q->whereDate('journal_entries.entry_date', '<=', (string) $params['date_to']);
            })
            ->with(['journalEntry', 'account'])
            ->orderBy('journal_entries.entry_date')
            ->orderBy('journal_entry_lines.id');

        return $query->paginate(max(1, min((int) ($params['per_page'] ?? 25), 100)));
    }

    /**
     * Debit/credit totals and net balance for an account.
     *
     * @return array{debit_total: string, credit_total: string, balance: string}
     */
    public function accountBalance(Account $account, ?string $asOfDate = null): array
    {
        return $this->aggregateAccount($account, $asOfDate);
    }

    /**
     * @return array{debit_total: string, credit_total: string, balance: string}
     */
    protected function aggregateAccount(Account $account, ?string $asOfDate = null): array
    {
        $query = JournalEntryLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->where('journal_entry_lines.account_id', $account->id)
            ->where('journal_entries.status', JournalEntryStatus::Posted->value);

        if ($asOfDate !== null && $asOfDate !== '') {
            $query->whereDate('journal_entries.entry_date', '<=', $asOfDate);
        }

        $debit = (string) ($query->clone()->sum('journal_entry_lines.debit') ?: '0');
        $credit = (string) ($query->clone()->sum('journal_entry_lines.credit') ?: '0');

        $debitTotal = Money::add($debit, '0');
        $creditTotal = Money::add($credit, '0');

        return [
            'debit_total' => $debitTotal,
            'credit_total' => $creditTotal,
            'balance' => Money::sub($debitTotal, $creditTotal),
        ];
    }
}
