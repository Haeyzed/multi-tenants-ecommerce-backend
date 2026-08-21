<?php

declare(strict_types=1);

namespace App\Services\Tenant\Accounting;

use App\Enums\Tenant\Accounting\JournalEntryStatus;
use App\Models\Tenant\JournalEntry;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Double-entry journal draft, post, unique source posting, and reverse.
 */
class JournalEntryService
{
    /**
     * Create a balanced draft journal entry with lines.
     *
     * @param  string  $reference
     * @param  ?string  $description
     * @param  string  $entryDate
     * @param  list<array{account_id: int, debit?: string|int|float, credit?: string|int|float, description?: string|null}>  $lines
     * @param  ?Model  $source
     * @param  ?string  $entryType
     * @return JournalEntry
     *
     * @throws ValidationException
     */
    public function createDraft(
        string $reference,
        ?string $description,
        string $entryDate,
        array $lines,
        ?Model $source = null,
        ?string $entryType = null,
    ): JournalEntry {
        $this->assertBalanced($lines);

        return DB::transaction(function () use ($reference, $description, $entryDate, $lines, $source, $entryType): JournalEntry {
            $entry = JournalEntry::query()->create([
                'reference' => $reference,
                'description' => $description,
                'entry_date' => $entryDate,
                'status' => JournalEntryStatus::Draft,
                'source_type' => $source?->getMorphClass(),
                'source_id' => $source?->getKey(),
                'entry_type' => $entryType,
            ]);

            foreach ($lines as $line) {
                $entry->lines()->create([
                    'account_id' => (int) $line['account_id'],
                    'debit' => $this->normalizeAmount($line['debit'] ?? '0'),
                    'credit' => $this->normalizeAmount($line['credit'] ?? '0'),
                    'description' => $line['description'] ?? null,
                ]);
            }

            return $entry->load('lines.account');
        });
    }

    /**
     * Post a draft journal entry; immutable afterwards.
     *
     * @param  JournalEntry  $entry
     * @return JournalEntry
     *
     * @throws ValidationException
     */
    public function post(JournalEntry $entry): JournalEntry
    {
        return DB::transaction(function () use ($entry): JournalEntry {
            /** @var JournalEntry $locked */
            $locked = JournalEntry::query()->whereKey($entry->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status === JournalEntryStatus::Posted) {
                throw ValidationException::withMessages([
                    'status' => 'Journal entry is already posted.',
                ]);
            }

            $locked->loadMissing('lines');
            $this->assertBalanced(
                $locked->lines->map(fn ($line): array => [
                    'account_id' => $line->account_id,
                    'debit' => (string) $line->debit,
                    'credit' => (string) $line->credit,
                ])->all()
            );

            $locked->status = JournalEntryStatus::Posted;
            $locked->posted_at = now();
            $locked->save();

            return $locked->fresh(['lines.account']) ?? $locked;
        });
    }

    /**
     * Post a unique journal for a morph source + entry_type, skipping if one already exists.
     *
     * @param  Model  $source
     * @param  string  $entryType
     * @param  callable  $builder
     * @return ?JournalEntry
     */
    public function postUnique(Model $source, string $entryType, callable $builder): ?JournalEntry
    {
        $existing = JournalEntry::query()
            ->where('source_type', $source->getMorphClass())
            ->where('source_id', $source->getKey())
            ->where('entry_type', $entryType)
            ->first();

        if ($existing !== null) {
            return $existing->status === JournalEntryStatus::Posted
                ? $existing
                : $this->post($existing);
        }

        /** @var JournalEntry $entry */
        $entry = $builder($this);

        if ($entry->status !== JournalEntryStatus::Posted) {
            $entry = $this->post($entry);
        }

        return $entry;
    }

    /**
     * Create and post a reversing entry for a posted journal.
     *
     * @param  JournalEntry  $entry
     * @return JournalEntry
     *
     * @throws ValidationException
     */
    public function reverse(JournalEntry $entry): JournalEntry
    {
        $entry->loadMissing('lines');

        if ($entry->status !== JournalEntryStatus::Posted) {
            throw ValidationException::withMessages([
                'status' => 'Only posted journal entries can be reversed.',
            ]);
        }

        $lines = $entry->lines->map(fn ($line): array => [
            'account_id' => $line->account_id,
            'debit' => (string) $line->credit,
            'credit' => (string) $line->debit,
            'description' => $line->description,
        ])->all();

        $reversal = $this->createDraft(
            reference: 'REV-'.$entry->reference.'-'.uniqid(),
            description: 'Reversal of '.$entry->reference.($entry->description ? ': '.$entry->description : ''),
            entryDate: now()->toDateString(),
            lines: $lines,
            source: $entry->source,
            entryType: $entry->entry_type !== null ? 'reverse_'.$entry->entry_type : 'reverse',
        );

        return $this->post($reversal);
    }

    /**
     * Assert balanced.
     *
     * @param  list<array{account_id: int, debit?: string|int|float, credit?: string|int|float, description?: string|null}>  $lines
     * @return void
     *
     * @throws ValidationException
     */
    public function assertBalanced(array $lines): void
    {
        if ($lines === []) {
            throw ValidationException::withMessages([
                'lines' => 'Journal entry requires at least one line.',
            ]);
        }

        $totalDebit = '0.00';
        $totalCredit = '0.00';

        foreach ($lines as $index => $line) {
            $debit = $this->normalizeAmount($line['debit'] ?? '0');
            $credit = $this->normalizeAmount($line['credit'] ?? '0');

            if (bccomp($debit, '0', 2) < 0 || bccomp($credit, '0', 2) < 0) {
                throw ValidationException::withMessages([
                    "lines.{$index}" => 'Debit and credit amounts must be non-negative.',
                ]);
            }

            if (bccomp($debit, '0', 2) > 0 && bccomp($credit, '0', 2) > 0) {
                throw ValidationException::withMessages([
                    "lines.{$index}" => 'A line cannot have both debit and credit amounts.',
                ]);
            }

            if (bccomp($debit, '0', 2) === 0 && bccomp($credit, '0', 2) === 0) {
                throw ValidationException::withMessages([
                    "lines.{$index}" => 'A line must have either a debit or a credit amount.',
                ]);
            }

            $totalDebit = Money::add($totalDebit, $debit);
            $totalCredit = Money::add($totalCredit, $credit);
        }

        if (bccomp($totalDebit, $totalCredit, 2) !== 0) {
            throw ValidationException::withMessages([
                'lines' => "Journal entry is unbalanced (debits {$totalDebit}, credits {$totalCredit}).",
            ]);
        }
    }

    /**
     * Normalize amount.
     *
     * @param  string|int|float  $amount
     * @return string
     */
    private function normalizeAmount(string|int|float $amount): string
    {
        return Money::add((string) $amount, '0');
    }
}
