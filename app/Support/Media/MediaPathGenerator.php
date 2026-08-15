<?php

declare(strict_types=1);

namespace App\Support\Media;

use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

/**
 * Separates landlord and tenant media paths on the configured disk.
 *
 * landlord/{media_id}/...
 * tenant/{tenant_id}/{media_id}/...
 */
class MediaPathGenerator implements PathGenerator
{
    public function getPath(Media $media): string
    {
        return $this->basePath($media).'/';
    }

    public function getPathForConversions(Media $media): string
    {
        return $this->basePath($media).'/conversions/';
    }

    public function getPathForResponsiveImages(Media $media): string
    {
        return $this->basePath($media).'/responsive-images/';
    }

    protected function basePath(Media $media): string
    {
        $mediaId = $media->getKey();

        if (tenancy()->initialized && tenancy()->tenant !== null) {
            $tenantKey = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) tenancy()->tenant->getTenantKey()) ?: 'unknown';

            return "tenant/{$tenantKey}/{$mediaId}";
        }

        return "landlord/{$mediaId}";
    }
}
