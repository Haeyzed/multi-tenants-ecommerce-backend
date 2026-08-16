<?php

declare(strict_types=1);

namespace App\Exceptions\Shipping;

use InvalidArgumentException;

/**
 * Thrown when a shipping carrier driver is not listed in config.
 */
class UnsupportedShippingCarrierException extends InvalidArgumentException
{
    public function __construct(string $name)
    {
        /** @var array<string, mixed> $drivers */
        $drivers = config('shipping.drivers', []);
        $known = implode(', ', array_keys($drivers));

        parent::__construct(
            "Unsupported shipping carrier [{$name}]. Known drivers: {$known}."
        );
    }
}
