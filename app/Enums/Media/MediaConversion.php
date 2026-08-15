<?php

declare(strict_types=1);

namespace App\Enums\Media;

/**
 * Canonical Spatie image conversion names.
 */
enum MediaConversion: string
{
    case Thumb = 'thumb';
    case Small = 'small';
    case Medium = 'medium';
    case Large = 'large';
}
