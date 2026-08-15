<?php

declare(strict_types=1);

namespace App\Enums\Media;

/**
 * Canonical media collection names used across the application.
 */
enum MediaCollection: string
{
    case Avatar = 'avatar';
    case Logo = 'logo';
    case Cover = 'cover';
    case Image = 'image';
    case OgImage = 'og-image';
    case Library = 'library';
    case Gallery = 'gallery';
    case Images = 'images';
    case Documents = 'documents';
    case Attachments = 'attachments';
    case Videos = 'videos';
    case Audio = 'audio';

    /**
     * Whether this collection is intended to hold a single media item.
     */
    public function isSingleFile(): bool
    {
        return in_array($this, [self::Avatar, self::Logo, self::Cover, self::Image, self::OgImage], true);
    }
}
