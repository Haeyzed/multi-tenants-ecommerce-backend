<?php

declare(strict_types=1);

namespace App\Services\Shipping;

use App\Contracts\Shipping\ShippingCarrierInterface;
use App\Services\Shipping\Carriers\FakeCarrier;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

/**
 * Resolves configured shipping carrier drivers.
 */
class ShippingCarrierManager
{
    public function __construct(private readonly Container $container) {}

    public function driver(?string $name = null): ShippingCarrierInterface
    {
        $name ??= (string) config('shipping.default', 'fake');

        return match ($name) {
            'fake' => $this->container->make(FakeCarrier::class),
            // Credential stubs exist in config/shipping.php; real HTTP clients are not wired yet.
            'dhl', 'gig', 'fedex', 'ups' => $this->container->make(FakeCarrier::class),
            default => throw new InvalidArgumentException("Unsupported shipping carrier [{$name}]."),
        };
    }

    /**
     * Resolve carrier for a shipping method code when configured.
     */
    public function forMethodCode(?string $code): ?ShippingCarrierInterface
    {
        if ($code === null || $code === '') {
            return null;
        }

        /** @var array<string, string> $map */
        $map = config('shipping.method_carriers', []);
        $driver = $map[$code] ?? null;

        return $driver !== null ? $this->driver($driver) : null;
    }
}
