<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Accounting;

use App\Http\Requests\BaseRequest;
use App\Rules\MoneyAmount;

/**
 * Validates journal entry create + post payloads.
 */
class StoreJournalEntryRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reference' => ['required', 'string', 'max:100', 'unique:journal_entries,reference'],
            'description' => ['sometimes', 'nullable', 'string'],
            'entry_date' => ['required', 'date'],
            'entry_type' => ['sometimes', 'nullable', 'string', 'max:100'],
            'post' => ['sometimes', 'boolean'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.account_id' => ['required', 'integer', 'exists:accounts,id'],
            'lines.*.debit' => ['sometimes', new MoneyAmount(allowZero: true)],
            'lines.*.credit' => ['sometimes', new MoneyAmount(allowZero: true)],
            'lines.*.description' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
