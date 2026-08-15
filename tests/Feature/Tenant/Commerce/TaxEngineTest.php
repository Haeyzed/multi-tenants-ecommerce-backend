<?php

declare(strict_types=1);

use App\Enums\Tenant\Tax\TaxAppliesTo;
use App\Models\Tenant\Tax;
use App\Models\Tenant\TaxRate;
use App\Models\Tenant\TaxRule;
use App\Models\Tenant\TaxZone;
use App\Models\Tenant\TaxZoneLocation;
use App\Services\Tenant\Commerce\CommerceSettingService;
use App\Services\Tenant\Tax\TaxAdminService;
use App\Services\Tenant\Tax\TaxService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    foreach (waveFourMigrationFiles() as $file) {
        $this->artisan('migrate', [
            '--path' => database_path('migrations/tenant/'.$file),
            '--realpath' => true,
            '--force' => true,
        ]);
    }
});

/**
 * @return list<string>
 */
function waveFourMigrationFiles(): array
{
    return [
        '2026_08_15_041024_create_customers_table.php',
        '2026_08_15_041028_create_customer_addresses_table.php',
        '2026_08_15_060001_create_commerce_settings_table.php',
        '2026_08_15_080001_create_taxes_table.php',
        '2026_08_15_080002_create_tax_rates_table.php',
        '2026_08_15_080003_create_tax_zones_table.php',
        '2026_08_15_080004_create_tax_zone_locations_table.php',
        '2026_08_15_080005_create_tax_rules_table.php',
    ];
}

test('tax service falls back to commerce tax_rate setting when no rules match', function (): void {
    app(CommerceSettingService::class)->set('tax_rate', '10');

    $result = app(TaxService::class)->calculateOrderTax(
        [['key' => 1, 'amount' => '200.00']],
        '0.00',
        ['country_id' => 999],
    );

    expect($result['uses_fallback'])->toBeTrue()
        ->and($result['tax_total'])->toBe('20.00')
        ->and($result['line_taxes'][0]['tax_amount'])->toBe('20.00');
});

test('tax service applies zone rule for product lines exclusively', function (): void {
    $tax = Tax::query()->create([
        'name' => 'VAT',
        'code' => 'VAT',
        'is_inclusive' => false,
    ]);
    TaxRate::query()->create(['tax_id' => $tax->id, 'rate' => '7.50']);

    $zone = TaxZone::query()->create(['name' => 'Lagos']);
    TaxZoneLocation::query()->create([
        'tax_zone_id' => $zone->id,
        'country_id' => 161,
        'state_id' => 306,
        'city_id' => null,
    ]);
    TaxRule::query()->create([
        'tax_id' => $tax->id,
        'tax_zone_id' => $zone->id,
        'applies_to' => TaxAppliesTo::Product,
    ]);

    $result = app(TaxService::class)->calculateOrderTax(
        [['key' => 'line-1', 'amount' => '100.00']],
        '50.00',
        ['country_id' => 161, 'state_id' => 306],
    );

    expect($result['uses_fallback'])->toBeFalse()
        ->and($result['tax_total'])->toBe('7.50')
        ->and($result['shipping_tax'])->toBe('0.00')
        ->and($result['line_taxes'][0]['tax_amount'])->toBe('7.50');
});

test('tax service supports inclusive tax extraction', function (): void {
    $tax = Tax::query()->create([
        'name' => 'Inclusive VAT',
        'code' => 'INCVAT',
        'is_inclusive' => true,
    ]);
    TaxRate::query()->create(['tax_id' => $tax->id, 'rate' => '10.00']);

    $zone = TaxZone::query()->create(['name' => 'National']);
    TaxZoneLocation::query()->create(['tax_zone_id' => $zone->id]);
    TaxRule::query()->create([
        'tax_id' => $tax->id,
        'tax_zone_id' => $zone->id,
        'applies_to' => TaxAppliesTo::All,
    ]);

    $line = app(TaxService::class)->calculateLineTax('110.00', []);

    expect($line['tax_amount'])->toBe('10.00');
});

test('tax admin service stores tax with rates', function (): void {
    $tax = app(TaxAdminService::class)->storeTax([
        'name' => 'Sales Tax',
        'code' => 'ST',
        'rates' => [['rate' => '5.00']],
    ]);

    expect($tax->rates)->toHaveCount(1)
        ->and((string) $tax->rates->first()->rate)->toBe('5.0000');
});
