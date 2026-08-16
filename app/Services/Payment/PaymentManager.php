<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Contracts\Payment\PaymentGateway;
use App\Services\Payment\Gateways\FakePaymentGateway;
use App\Services\Payment\Gateways\FlutterwaveGateway;
use App\Services\Payment\Gateways\MoniepointGateway;
use App\Services\Payment\Gateways\MonnifyGateway;
use App\Services\Payment\Gateways\PaystackGateway;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

/**
 * Resolves configured payment gateway drivers.
 */
class PaymentManager
{
    /**
     * Create a new payment manager instance.
     */
    public function __construct(private readonly Container $container) {}

    /**
     * Resolve a payment gateway driver by name.
     */
    public function driver(?string $name = null): PaymentGateway
    {
        $name ??= (string) config('payment.default', 'paystack');

        return match ($name) {
            'paystack' => $this->container->make(PaystackGateway::class),
            'flutterwave' => $this->container->make(FlutterwaveGateway::class),
            'monnify' => $this->container->make(MonnifyGateway::class),
            'moniepoint' => $this->container->make(MoniepointGateway::class),
            'fake' => $this->container->make(FakePaymentGateway::class),
            default => throw new InvalidArgumentException("Unsupported payment driver [{$name}]."),
        };
    }

    /**
     * Registered driver names.
     *
     * @return list<string>
     */
    public function drivers(): array
    {
        return [
            'paystack',
            'flutterwave',
            'monnify',
            'moniepoint',
            'fake',
        ];
    }
}
