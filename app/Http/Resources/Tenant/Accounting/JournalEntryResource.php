<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Accounting;

use App\Models\Tenant\JournalEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin JournalEntry
 */
class JournalEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var JournalEntry $entry */
        $entry = $this->resource;

        return [
            'id' => $entry->id,
            'reference' => $entry->reference,
            'description' => $entry->description,
            'entry_date' => $entry->entry_date?->toDateString(),
            'status' => $entry->status,
            'source_type' => $entry->source_type,
            'source_id' => $entry->source_id,
            'entry_type' => $entry->entry_type,
            'posted_at' => $entry->posted_at,
            'lines' => JournalEntryLineResource::collection($this->whenLoaded('lines')),
            'created_at' => $entry->created_at,
            'updated_at' => $entry->updated_at,
        ];
    }
}
