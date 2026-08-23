<?php

declare(strict_types=1);

namespace App\Enums\Content;

/**
 * Shared CMS publication status for landlord and tenant content.
 */
enum ContentStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Published = 'published';
    case Archived = 'archived';
}
