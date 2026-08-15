<?php

declare(strict_types=1);

namespace App\Services\Notification\Sms;

use App\Contracts\Notification\SmsProvider;
use App\Services\Notification\Sms\Providers\AfricasTalkingSmsProvider;
use App\Services\Notification\Sms\Providers\AmazonSnsSmsProvider;
use App\Services\Notification\Sms\Providers\BulkSmsProvider;
use App\Services\Notification\Sms\Providers\HubtelSmsProvider;
use App\Services\Notification\Sms\Providers\MessageBirdSmsProvider;
use App\Services\Notification\Sms\Providers\TermiiSmsProvider;
use App\Services\Notification\Sms\Providers\TwilioSmsProvider;
use App\Services\Notification\Sms\Providers\VonageSmsProvider;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

/**
 * Resolves configured SMS provider drivers.
 */
class SmsManager
{
    public function __construct(private readonly Container $container) {}

    /**
     * Resolve an SMS provider driver by name.
     */
    public function driver(?string $name = null): SmsProvider
    {
        $name ??= (string) config('notifications.sms.default', 'null');

        return match ($name) {
            'null' => $this->container->make(NullSmsProvider::class),
            'twilio' => $this->container->make(TwilioSmsProvider::class),
            'vonage' => $this->container->make(VonageSmsProvider::class),
            'messagebird' => $this->container->make(MessageBirdSmsProvider::class),
            'sns', 'amazon_sns' => $this->container->make(AmazonSnsSmsProvider::class),
            'termii' => $this->container->make(TermiiSmsProvider::class),
            'africastalking' => $this->container->make(AfricasTalkingSmsProvider::class),
            'bulksms' => $this->container->make(BulkSmsProvider::class),
            'hubtel' => $this->container->make(HubtelSmsProvider::class),
            default => throw new InvalidArgumentException("Unsupported SMS driver [{$name}]."),
        };
    }
}
