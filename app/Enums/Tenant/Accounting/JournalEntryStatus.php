<?php

declare(strict_types=1);

namespace App\Enums\Tenant\Accounting;

/**
 * Journal entry posting status.
 */
enum JournalEntryStatus: string
{
    case Draft = 'draft';
    case Posted = 'posted';
}
