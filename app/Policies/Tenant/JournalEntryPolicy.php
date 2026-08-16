<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Models\Tenant\JournalEntry;
use App\Models\Tenant\User;

/**
 * Authorization for journal entries.
 */
class JournalEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('accounting.view');
    }

    public function view(User $user, JournalEntry $journalEntry): bool
    {
        return $user->can('accounting.view');
    }

    public function create(User $user): bool
    {
        return $user->can('journal_entries.create') || $user->can('accounting.manage');
    }

    public function post(User $user, ?JournalEntry $journalEntry = null): bool
    {
        return $user->can('journal_entries.post') || $user->can('accounting.manage');
    }

    public function reverse(User $user, JournalEntry $journalEntry): bool
    {
        return $user->can('journal_entries.reverse') || $user->can('accounting.manage');
    }
}
