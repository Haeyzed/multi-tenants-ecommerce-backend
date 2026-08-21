<?php

declare(strict_types=1);

use App\Services\Landlord\Tenant\TenantService;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

uses(TestCase::class);

test('tenant provisioning config defaults to five minutes', function (): void {
    expect(config('tenancy.provisioning.max_execution_time'))->toBe(300);
});

test('tenant service extends execution time limit during provisioning', function (): void {
    Config::set('tenancy.provisioning.max_execution_time', 120);

    $service = app(TenantService::class);

    $method = new ReflectionMethod(TenantService::class, 'extendProvisioningTimeLimit');
    $method->setAccessible(true);
    $method->invoke($service);

    expect(ini_get('max_execution_time'))->toBe('120');
});
