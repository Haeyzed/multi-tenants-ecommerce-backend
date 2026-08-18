<?php

declare(strict_types=1);

namespace App\Enums\Tenant\HR;

/**
 * How an application entered the tenant ATS.
 */
enum ApplicationSource: string
{
    case Website = 'website';
    case Referral = 'referral';
    case Internal = 'internal';
    case Other = 'other';
}
