<?php

declare(strict_types=1);

namespace App\Services\Tenant\Delivery;

use App\Contracts\Delivery\DriverAssignmentStrategyInterface;
use App\Models\Tenant\Delivery;
use App\Models\Tenant\Driver;
use App\Services\Tenant\Delivery\Assignment\AutomaticDriverAssignmentStrategy;
use App\Services\Tenant\Delivery\Assignment\ManualDriverAssignmentStrategy;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

/**
 * Resolves the configured driver assignment strategy.
 */
class DriverAssignmentManager
{
    public function __construct(private readonly Container $container) {}

    public function strategy(?string $name = null): DriverAssignmentStrategyInterface
    {
        $name ??= (string) config('delivery.assignment.strategy', 'manual');

        return match ($name) {
            'manual' => $this->container->make(ManualDriverAssignmentStrategy::class),
            'automatic' => $this->container->make(AutomaticDriverAssignmentStrategy::class),
            default => throw new InvalidArgumentException("Unsupported driver assignment strategy [{$name}]."),
        };
    }

    public function assign(Delivery $delivery): ?Driver
    {
        return $this->strategy()->assign($delivery);
    }
}
