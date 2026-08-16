<?php

declare(strict_types=1);

namespace App\Enums\Landlord;

/**
 * Supported payment gateway drivers.
 */
enum PaymentProvider: string
{
    case Paystack = 'paystack';
    case Flutterwave = 'flutterwave';
    case Monnify = 'monnify';
    case Moniepoint = 'moniepoint';
    case Fake = 'fake';
}
