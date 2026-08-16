<?php

declare(strict_types=1);

namespace App\DTO\Shipping;

/**
 * Result of fetching a carrier shipping label.
 */
readonly class ShipmentLabelResult
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public bool $successful,
        public ?string $contentType = null,
        public ?string $contentBase64 = null,
        public ?string $url = null,
        public array $raw = [],
        public ?string $message = null,
    ) {}
}
