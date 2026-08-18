<?php

declare(strict_types=1);

namespace App\Enums\Tenant\HR;

/**
 * Recruitment job opening lifecycle.
 *
 * `open` is accepted as a legacy alias of `published`.
 */
enum JobOpeningStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Open = 'open';
    case Paused = 'paused';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    public function isPubliclyListable(): bool
    {
        return $this === self::Published || $this === self::Open;
    }

    public function acceptsApplications(): bool
    {
        return $this->isPubliclyListable();
    }

    public function isActiveListing(): bool
    {
        return $this->isPubliclyListable() || $this === self::Paused;
    }

    public static function fromInput(self|string $value): self
    {
        $status = $value instanceof self ? $value : self::from($value);

        return $status === self::Open ? self::Published : $status;
    }
}
