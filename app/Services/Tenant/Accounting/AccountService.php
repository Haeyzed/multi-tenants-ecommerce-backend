<?php

declare(strict_types=1);

namespace App\Services\Tenant\Accounting;

use App\Models\Tenant\Account;
use App\Models\Tenant\JournalEntry;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Account and journal listing helpers for admin APIs.
 */
class AccountService
{
    /**
     * Retrieve a paginated list of resources.
     *
     * @param  array{type?: string|null, is_active?: bool|null, search?: string|null, per_page?: int|null}  $params
     * @return LengthAwarePaginator<int, Account>
     */
    public function list(array $params = []): LengthAwarePaginator
    {
        $query = Account::query()->orderBy('code');

        if (! empty($params['type'])) {
            $query->where('type', $params['type']);
        }

        if (isset($params['is_active'])) {
            $query->where('is_active', (bool) $params['is_active']);
        }

        if (! empty($params['search'])) {
            $search = (string) $params['search'];
            $query->where(function ($q) use ($search): void {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        return $query->paginate(max(1, min((int) ($params['per_page'] ?? 50), 100)));
    }

    /**
     * List journal entries.
     *
     * @param  array{status?: string|null, entry_type?: string|null, per_page?: int|null}  $params
     * @return LengthAwarePaginator<int, JournalEntry>
     */
    public function listJournalEntries(array $params = []): LengthAwarePaginator
    {
        $query = JournalEntry::query()->with('lines.account')->latest('id');

        if (! empty($params['status'])) {
            $query->where('status', $params['status']);
        }

        if (! empty($params['entry_type'])) {
            $query->where('entry_type', $params['entry_type']);
        }

        return $query->paginate(max(1, min((int) ($params['per_page'] ?? 15), 100)));
    }

    /**
     * Show journal entry.
     *
     * @param  JournalEntry  $entry
     * @return JournalEntry
     */
    public function showJournalEntry(JournalEntry $entry): JournalEntry
    {
        return $entry->load(['lines.account', 'source']);
    }
}
