<?php

declare(strict_types=1);

namespace App\Services\Shipping;

use App\Contracts\Shipping\ShippingCarrierInterface;
use App\DTO\Shipping\ShipmentCancellationResult;
use App\DTO\Shipping\ShipmentLabelResult;
use App\Exceptions\Shipping\UnsupportedShippingCarrierException;
use App\Services\Shipping\Carriers\FakeCarrier;
use Illuminate\Contracts\Container\Container;

/**
 * Resolves configured shipping carrier drivers.
 *
 * Known stub drivers (dhl, gig, fedex, ups, local) resolve to FakeCarrier as
 * scaffold aliases until live HTTP clients exist.
 */
class ShippingCarrierManager
{
    /**
     * Driver names that currently resolve to FakeCarrier until live clients ship.
     *
     * @var list<string>
     */
    private const SCAFFOLD_ALIASES = ['dhl', 'gig', 'fedex', 'ups', 'local'];

    public function __construct(private readonly Container $container) {}

    /**
     * Configured carrier driver keys.
     *
     * @return list<string>
     */
    public function drivers(): array
    {
        /** @var array<string, mixed> $drivers */
        $drivers = config('shipping.drivers', []);

        return array_keys($drivers);
    }

    public function driver(?string $name = null): ShippingCarrierInterface
    {
        $name ??= (string) config('shipping.default', 'fake');

        if (! in_array($name, $this->drivers(), true)) {
            throw new UnsupportedShippingCarrierException($name);
        }

        // Scaffold aliases and the fake driver share FakeCarrier until live clients exist.
        return match (true) {
            $name === 'fake', $this->isScaffoldAlias($name) => $this->container->make(FakeCarrier::class),
            default => throw new UnsupportedShippingCarrierException($name),
        };
    }

    /**
     * Whether the driver is a stub alias that currently resolves to FakeCarrier.
     */
    public function isScaffoldAlias(string $name): bool
    {
        return in_array($name, self::SCAFFOLD_ALIASES, true);
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

    /**
     * @param  array<string, mixed>  $context
     */
    public function cancelShipment(string $trackingNumber, array $context = [], ?string $driver = null): ShipmentCancellationResult
    {
        return $this->driver($driver)->cancelShipment($trackingNumber, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function getLabel(string $trackingNumber, array $context = [], ?string $driver = null): ShipmentLabelResult
    {
        return $this->driver($driver)->getLabel($trackingNumber, $context);
    }
}
