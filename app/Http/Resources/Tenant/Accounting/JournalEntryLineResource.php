<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Accounting;

use App\Models\Tenant\JournalEntryLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin JournalEntryLine
 */
class JournalEntryLineResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var JournalEntryLine $line */
        $line = $this->resource;

        return [
            'id' => $line->id,
            'account_id' => $line->account_id,
            'debit' => $line->debit,
            'credit' => $line->credit,
            'description' => $line->description,
            'account' => $this->whenLoaded('account', fn () => new AccountResource($line->account)),
            'journal_entry' => $this->whenLoaded('journalEntry', fn () => [
                'id' => $line->journalEntry->id,
                'reference' => $line->journalEntry->reference,
                'description' => $line->journalEntry->description,
                'entry_date' => $line->journalEntry->entry_date?->toDateString(),
                'entry_type' => $line->journalEntry->entry_type,
                'status' => $line->journalEntry->status?->value,
            ]),
        ];
    }
}
